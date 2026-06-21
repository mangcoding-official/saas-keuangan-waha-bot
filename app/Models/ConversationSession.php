<?php

namespace App\Models;

use App\Enums\ConversationIntentType;
use App\Enums\ConversationSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'status',
        'active_lock',
        'current_state',
        'intent_type',
        'draft_payload',
        'source_message_id',
        'last_message_at',
        'expires_at',
        'completed_at',
        'cancelled_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationSessionStatus::class,
            'intent_type' => ConversationIntentType::class,
            'draft_payload' => 'array',
            'last_message_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class);
    }
}
