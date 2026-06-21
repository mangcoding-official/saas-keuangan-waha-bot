<?php

namespace App\Services;

use App\Enums\AuditActorSource;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;

class TenantAuditLogService
{
    /**
     * @param  array<string, mixed>|null  $beforePayload
     * @param  array<string, mixed>|null  $afterPayload
     */
    public function logTenantUser(
        TenantUser $actor,
        string $entityType,
        int $entityId,
        string $action,
        ?array $beforePayload = null,
        ?array $afterPayload = null,
    ): void {
        DB::table('audit_logs')->insert([
            'tenant_id' => $actor->tenant_id,
            'actor_source' => AuditActorSource::TENANT_USER->value,
            'actor_tenant_user_id' => $actor->id,
            'actor_platform_admin_user_id' => null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_payload' => $beforePayload ? json_encode($beforePayload, JSON_THROW_ON_ERROR) : null,
            'after_payload' => $afterPayload ? json_encode($afterPayload, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
