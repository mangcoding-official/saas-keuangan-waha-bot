<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentPreviewController extends Controller
{
    public function show(int $attachmentId): StreamedResponse
    {
        $attachment = Attachment::query()->findOrFail($attachmentId);

        abort_unless(Storage::disk($attachment->storage_disk)->exists($attachment->storage_path), 404);

        $extension = match ($attachment->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return Storage::disk($attachment->storage_disk)->response(
            $attachment->storage_path,
            'internal-attachment-'.$attachment->id.'.'.$extension,
            [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
