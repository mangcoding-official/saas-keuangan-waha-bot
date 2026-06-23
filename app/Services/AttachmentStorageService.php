<?php

namespace App\Services;

use App\Exceptions\AttachmentException;
use App\Models\Attachment;
use App\Models\ConversationSession;
use App\Models\TenantUser;
use App\Services\Waha\WahaClient;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AttachmentStorageService
{
    public function __construct(
        private readonly WahaClient $wahaClient,
    ) {}

    /**
     * @param  array{url:string,mime_type:?string,file_name:?string,file_size:?int,width:?int,height:?int}  $media
     */
    public function storeFromWaha(
        TenantUser $tenantUser,
        ConversationSession $session,
        string $sourceMessageId,
        array $media,
    ): Attachment {
        $existing = Attachment::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('source_message_id', $sourceMessageId)
            ->first();

        if ($existing && Storage::disk($existing->storage_disk)->exists($existing->storage_path)) {
            return $existing;
        }

        try {
            $contents = $this->wahaClient->downloadMedia($media['url']);
        } catch (Throwable $exception) {
            throw new AttachmentException('Lampiran tidak dapat diunduh dari WAHA. Silakan kirim ulang.', previous: $exception);
        }

        $fileSize = strlen($contents);
        $maxBytes = (int) config('services.waha.attachments.max_bytes', 5 * 1024 * 1024);

        if ($fileSize === 0) {
            throw new AttachmentException('File lampiran kosong atau tidak dapat diunduh.');
        }

        if ($fileSize > $maxBytes) {
            throw new AttachmentException('Ukuran lampiran melebihi batas 5 MB.');
        }

        $mimeType = $this->detectMimeType($contents);
        $extensions = (array) config('services.waha.attachments.allowed_mime_types', [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ]);

        if (! isset($extensions[$mimeType])) {
            throw new AttachmentException('Lampiran harus berupa gambar JPG, PNG, atau WebP.');
        }

        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false) {
            throw new AttachmentException('Isi file lampiran bukan gambar yang valid.');
        }

        $disk = (string) config('services.waha.attachments.disk', 'local');
        $path = sprintf(
            'attachments/%d/%s.%s',
            $tenantUser->tenant_id,
            hash('sha256', $sourceMessageId),
            $extensions[$mimeType],
        );

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new AttachmentException('Lampiran gagal disimpan. Silakan kirim ulang.');
        }

        $attributes = [
            'tenant_id' => $tenantUser->tenant_id,
            'uploaded_by_user_id' => $tenantUser->id,
            'conversation_session_id' => $session->id,
            'source_message_id' => $sourceMessageId,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_file_name' => $this->normalizeFileName($media['file_name'] ?? null),
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
        ];

        try {
            if ($existing) {
                $existing->forceFill($attributes)->save();

                return $existing->fresh();
            }

            return Attachment::query()->create($attributes);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    private function detectMimeType(string $contents): string
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $fileInfo->buffer($contents);
    }

    private function normalizeFileName(?string $fileName): ?string
    {
        $name = trim(basename(str_replace('\\', '/', (string) $fileName)));

        if ($name === '') {
            return null;
        }

        return mb_substr($name, 0, 255);
    }
}
