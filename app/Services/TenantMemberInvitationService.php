<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\TenantUser;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantMemberInvitationService
{
    public function __construct(
        private readonly ActivationCodeService $activationCodeService,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    /**
     * @param  array{name: string, whatsapp_number: string}  $payload
     * @return array{member: TenantUser, activation_code: string}
     */
    public function invite(TenantUser $owner, array $payload): array
    {
        $this->assertOwnerCanInvite($owner);
        $normalizedNumber = $this->normalizeNumber((string) $payload['whatsapp_number'], 'whatsapp_number');

        return DB::transaction(function () use ($owner, $payload, $normalizedNumber): array {
            $member = TenantUser::query()->create([
                'tenant_id' => $owner->tenant_id,
                'name' => trim((string) $payload['name']),
                'email' => null,
                'password' => null,
                'role' => UserRole::MEMBER,
                'user_status' => UserStatus::ACTIVE,
                'whatsapp_number' => trim((string) $payload['whatsapp_number']),
                'whatsapp_number_normalized' => $normalizedNumber,
                'verification_status' => VerificationStatus::PENDING_VERIFICATION,
                'invited_by_user_id' => $owner->id,
            ]);

            return [
                'member' => $member,
                'activation_code' => $this->activationCodeService->issue($member->id, 'member_created'),
            ];
        });
    }

    /**
     * Karena code disimpan dalam hash, resend praktis harus menerbitkan code baru yang menggantikan code aktif lama.
     */
    public function resend(TenantUser $owner, TenantUser $member): string
    {
        $this->assertActionAllowed($owner, $member);

        return $this->activationCodeService->issue($member->id, 'resent');
    }

    public function regenerate(TenantUser $owner, TenantUser $member): string
    {
        $this->assertActionAllowed($owner, $member);

        return $this->activationCodeService->issue($member->id, 'regenerated');
    }

    private function assertOwnerCanInvite(TenantUser $owner): void
    {
        $userCount = TenantUser::query()
            ->where('tenant_id', $owner->tenant_id)
            ->count();

        if ($userCount >= (int) config('platform.limits.tenant_users')) {
            throw ValidationException::withMessages([
                'name' => 'Slot user tenant sudah penuh. Maksimal 1 owner dan 4 member per tenant.',
            ]);
        }
    }

    private function assertActionAllowed(TenantUser $owner, TenantUser $member): void
    {
        if ($member->tenant_id !== $owner->tenant_id || $member->role !== UserRole::MEMBER) {
            throw ValidationException::withMessages([
                'members' => 'Member tidak ditemukan di tenant ini.',
            ]);
        }

        if ($member->verification_status === VerificationStatus::VERIFIED) {
            throw ValidationException::withMessages([
                'members' => 'User ini sudah verified. Activation code tidak perlu dikirim ulang.',
            ]);
        }
    }

    private function normalizeNumber(string $rawNumber, string $field): string
    {
        try {
            $normalized = $this->phoneNumberNormalizer->normalize($rawNumber);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        $userExists = DB::table('tenant_users')
            ->where('whatsapp_number_normalized', $normalized)
            ->exists();

        if ($userExists) {
            throw ValidationException::withMessages([
                $field => 'Nomor WhatsApp ini sudah dipakai user tenant lain.',
            ]);
        }

        $botNumberExists = DB::table('bot_instances')
            ->where('is_active', true)
            ->where('bot_whatsapp_number_normalized', $normalized)
            ->exists();

        if ($botNumberExists) {
            throw ValidationException::withMessages([
                $field => 'Nomor member tidak boleh sama dengan nomor bot aktif.',
            ]);
        }

        return $normalized;
    }
}
