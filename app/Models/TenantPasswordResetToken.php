<?php

namespace App\Models;

use App\Enums\PasswordResetTokenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPasswordResetToken extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_user_id',
        'lookup_key',
        'token_hash',
        'status',
        'requested_whatsapp_number',
        'requested_whatsapp_number_normalized',
        'requested_ip_address',
        'requested_user_agent',
        'delivery_channel',
        'failure_reason',
        'requested_at',
        'sent_at',
        'expires_at',
        'used_at',
        'expired_at',
        'invalidated_at',
        'consumed_ip_address',
        'consumed_user_agent',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => PasswordResetTokenStatus::class,
            'requested_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'expired_at' => 'datetime',
            'invalidated_at' => 'datetime',
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
