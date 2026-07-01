<?php

namespace App\Models;

use App\Enums\InviteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerRegistrationInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'code_normalized',
        'status',
        'invited_email',
        'note',
        'expires_at',
        'used_at',
        'revoked_at',
        'created_by_platform_admin_user_id',
        'revoked_by_platform_admin_user_id',
        'used_by_tenant_id',
        'used_by_tenant_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => InviteStatus::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdminUser::class, 'created_by_platform_admin_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdminUser::class, 'revoked_by_platform_admin_user_id');
    }

    public function usedByTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'used_by_tenant_id');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'used_by_tenant_user_id');
    }
}
