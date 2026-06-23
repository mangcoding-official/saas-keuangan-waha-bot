<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attachment extends Model
{
    protected $fillable = [
        'tenant_id',
        'uploaded_by_user_id',
        'conversation_session_id',
        'source_message_id',
        'storage_disk',
        'storage_path',
        'original_file_name',
        'mime_type',
        'file_size',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_user_id');
    }

    public function conversationSession(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'attachment_transaction')
            ->withPivot('created_at');
    }
}
