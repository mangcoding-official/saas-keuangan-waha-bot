<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(int $attachmentId): StreamedResponse
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();

        $attachment = Attachment::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('transactions', function ($query) use ($user): void {
                if ($user->role === UserRole::MEMBER) {
                    $query->where('transactions.recorded_by_user_id', $user->id);
                }
            })
            ->findOrFail($attachmentId);

        abort_unless(Storage::disk($attachment->storage_disk)->exists($attachment->storage_path), 404);

        $extension = match ($attachment->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return Storage::disk($attachment->storage_disk)->response(
            $attachment->storage_path,
            'bukti-transaksi-'.$attachment->id.'.'.$extension,
            [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
