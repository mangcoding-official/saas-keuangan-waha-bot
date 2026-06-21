<?php

namespace App\Models;

use App\Enums\AuditActorSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor_source',
        'actor_tenant_user_id',
        'actor_platform_admin_user_id',
        'entity_type',
        'entity_id',
        'action',
        'before_payload',
        'after_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_source' => AuditActorSource::class,
            'before_payload' => 'array',
            'after_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actorTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'actor_tenant_user_id');
    }
}
