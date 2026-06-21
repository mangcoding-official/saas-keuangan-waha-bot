<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\PlatformAdminUser;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantVerificationCodeService
{
    public function __construct(
        private readonly ActivationCodeService $activationCodeService,
        private readonly ActivationCodeDeliveryService $activationCodeDeliveryService,
    ) {
    }

    /**
     * @return array{code: string, whatsapp_sent: bool}
     */
    public function resendForTenantOwner(TenantUser $owner, TenantUser $targetUser): array
    {
        $this->assertTenantOwnerCanManage($owner, $targetUser);
        $this->assertPendingUser($targetUser);

        $code = $this->activationCodeService->issue($targetUser->id, 'resent');

        return [
            'code' => $code,
            'whatsapp_sent' => $this->activationCodeDeliveryService->send($targetUser, $code, 'resent'),
        ];
    }

    /**
     * @return array{code: string, whatsapp_sent: bool}
     */
    public function regenerateForTenantOwner(TenantUser $owner, TenantUser $targetUser): array
    {
        $this->assertTenantOwnerCanManage($owner, $targetUser);
        $this->assertPendingUser($targetUser);

        $code = $this->activationCodeService->issue($targetUser->id, 'regenerated');

        return [
            'code' => $code,
            'whatsapp_sent' => $this->activationCodeDeliveryService->send($targetUser, $code, 'regenerated'),
        ];
    }

    /**
     * @return array{code: string, whatsapp_sent: bool}
     */
    public function resendForPlatformAdmin(PlatformAdminUser $admin, TenantUser $targetUser): array
    {
        $this->assertPendingUser($targetUser);
        $code = $this->activationCodeService->issue($targetUser->id, 'resent_by_admin');

        $this->recordPlatformAudit($admin, $targetUser, 'verification_code_resent');

        return [
            'code' => $code,
            'whatsapp_sent' => $this->activationCodeDeliveryService->send($targetUser, $code, 'resent_by_admin'),
        ];
    }

    /**
     * @return array{code: string, whatsapp_sent: bool}
     */
    public function regenerateForPlatformAdmin(PlatformAdminUser $admin, TenantUser $targetUser): array
    {
        $this->assertPendingUser($targetUser);
        $code = $this->activationCodeService->issue($targetUser->id, 'regenerated_by_admin');

        $this->recordPlatformAudit($admin, $targetUser, 'verification_code_regenerated');

        return [
            'code' => $code,
            'whatsapp_sent' => $this->activationCodeDeliveryService->send($targetUser, $code, 'regenerated_by_admin'),
        ];
    }

    private function assertTenantOwnerCanManage(TenantUser $owner, TenantUser $targetUser): void
    {
        if ($owner->role !== UserRole::OWNER) {
            throw ValidationException::withMessages([
                'members' => 'Hanya owner tenant yang boleh mengelola activation code.',
            ]);
        }

        if ($targetUser->tenant_id !== $owner->tenant_id || ! in_array($targetUser->role, [UserRole::OWNER, UserRole::MEMBER], true)) {
            throw ValidationException::withMessages([
                'members' => 'User tenant tidak ditemukan di workspace ini.',
            ]);
        }
    }

    private function assertPendingUser(TenantUser $targetUser): void
    {
        if ($targetUser->verification_status === VerificationStatus::VERIFIED) {
            throw ValidationException::withMessages([
                'members' => 'User ini sudah verified. Activation code tidak perlu dikirim ulang.',
            ]);
        }
    }

    private function recordPlatformAudit(PlatformAdminUser $admin, TenantUser $targetUser, string $action): void
    {
        DB::table('platform_admin_audit_logs')->insert([
            'platform_admin_user_id' => $admin->id,
            'action' => $action,
            'target_entity_type' => 'tenant_user',
            'target_entity_id' => $targetUser->id,
            'reason_note' => 'Verification support action',
            'before_snapshot' => json_encode([
                'verification_status' => $targetUser->verification_status->value,
            ], JSON_THROW_ON_ERROR),
            'after_snapshot' => json_encode([
                'verification_status' => $targetUser->verification_status->value,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
