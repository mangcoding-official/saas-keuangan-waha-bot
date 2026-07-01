<?php

namespace App\Services;

use App\Enums\InviteStatus;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerRegistrationInviteService
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    public function createForPlatformAdmin(PlatformAdminUser $admin, array $payload): OwnerRegistrationInvite
    {
        $code = $this->generateUniqueCode();
        $targetWhatsapp = $this->normalizeTargetWhatsapp((string) ($payload['invited_whatsapp_number'] ?? ''));

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => $code,
            'code_normalized' => $this->normalizeCode($code),
            'status' => InviteStatus::PENDING,
            'invited_email' => isset($payload['invited_email']) && trim((string) $payload['invited_email']) !== ''
                ? mb_strtolower(trim((string) $payload['invited_email']))
                : null,
            'invited_whatsapp_number' => trim((string) ($payload['invited_whatsapp_number'] ?? '')) !== ''
                ? trim((string) $payload['invited_whatsapp_number'])
                : null,
            'invited_whatsapp_number_normalized' => $targetWhatsapp,
            'note' => isset($payload['note']) && trim((string) $payload['note']) !== ''
                ? trim((string) $payload['note'])
                : null,
            'expires_at' => isset($payload['expires_at']) && trim((string) $payload['expires_at']) !== ''
                ? Carbon::parse((string) $payload['expires_at'])
                : null,
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $this->recordPlatformAudit(
            admin: $admin,
            invite: $invite,
            action: 'owner_registration_invite_created',
            reason: 'Invite dibuat untuk onboarding alpha',
            before: null,
            after: $this->snapshot($invite),
        );

        return $invite;
    }

    public function revokeForPlatformAdmin(PlatformAdminUser $admin, OwnerRegistrationInvite $invite, ?string $reason = null): OwnerRegistrationInvite
    {
        return DB::transaction(function () use ($admin, $invite, $reason): OwnerRegistrationInvite {
            /** @var OwnerRegistrationInvite|null $lockedInvite */
            $lockedInvite = OwnerRegistrationInvite::query()
                ->whereKey($invite->id)
                ->lockForUpdate()
                ->first();

            if ($lockedInvite === null) {
                throw ValidationException::withMessages([
                    'invite' => 'Invite tidak ditemukan.',
                ]);
            }

            $this->expireIfNeeded($lockedInvite);

            if ($lockedInvite->status !== InviteStatus::PENDING) {
                throw ValidationException::withMessages([
                    'invite' => 'Hanya invite pending yang bisa direvoke.',
                ]);
            }

            $before = $this->snapshot($lockedInvite);

            $lockedInvite->forceFill([
                'status' => InviteStatus::REVOKED,
                'revoked_at' => now(),
                'revoked_by_platform_admin_user_id' => $admin->id,
            ])->save();

            $this->recordPlatformAudit(
                admin: $admin,
                invite: $lockedInvite,
                action: 'owner_registration_invite_revoked',
                reason: trim((string) $reason) !== '' ? trim((string) $reason) : 'Invite dicabut oleh super admin',
                before: $before,
                after: $this->snapshot($lockedInvite),
            );

            return $lockedInvite->fresh();
        });
    }

    public function consumeForOwnerRegistration(string $rawCode, string $ownerEmail, Tenant $tenant, TenantUser $owner): OwnerRegistrationInvite
    {
        return DB::transaction(function () use ($rawCode, $ownerEmail, $tenant, $owner): OwnerRegistrationInvite {
            /** @var OwnerRegistrationInvite|null $invite */
            $invite = OwnerRegistrationInvite::query()
                ->where('code_normalized', $this->normalizeCode($rawCode))
                ->lockForUpdate()
                ->first();

            if ($invite === null) {
                throw ValidationException::withMessages([
                    'invite_code' => 'Kode invite tidak valid.',
                ]);
            }

            $this->expireIfNeeded($invite);

            if ($invite->status !== InviteStatus::PENDING) {
                throw ValidationException::withMessages([
                    'invite_code' => $this->invalidStatusMessage($invite->status),
                ]);
            }

            if ($invite->invited_email !== null && $invite->invited_email !== mb_strtolower(trim($ownerEmail))) {
                throw ValidationException::withMessages([
                    'invite_code' => 'Kode invite ini hanya berlaku untuk email yang ditentukan tim internal.',
                ]);
            }

            $invite->forceFill([
                'status' => InviteStatus::USED,
                'used_at' => now(),
                'used_by_tenant_id' => $tenant->id,
                'used_by_tenant_user_id' => $owner->id,
            ])->save();

            return $invite->fresh();
        });
    }

    /**
     * @return array{code:string,status:string,invited_email:?string,invited_whatsapp_number:?string,expires_at:?string,used_at:?string,revoked_at:?string,used_by_tenant_id:?int,used_by_tenant_user_id:?int,note:?string}
     */
    public function snapshot(OwnerRegistrationInvite $invite): array
    {
        return [
            'code' => $invite->code,
            'status' => $invite->status->value,
            'invited_email' => $invite->invited_email,
            'invited_whatsapp_number' => $invite->invited_whatsapp_number,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'used_at' => $invite->used_at?->toIso8601String(),
            'revoked_at' => $invite->revoked_at?->toIso8601String(),
            'used_by_tenant_id' => $invite->used_by_tenant_id,
            'used_by_tenant_user_id' => $invite->used_by_tenant_user_id,
            'note' => $invite->note,
        ];
    }

    public function normalizeCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(trim($code))) ?: '';
    }

    private function normalizeTargetWhatsapp(string $rawNumber): ?string
    {
        if (trim($rawNumber) === '') {
            return null;
        }

        try {
            return $this->phoneNumberNormalizer->normalize($rawNumber);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'invited_whatsapp_number' => $exception->getMessage(),
            ]);
        }
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'ALPHA-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
            $exists = OwnerRegistrationInvite::query()
                ->where('code_normalized', $this->normalizeCode($code))
                ->exists();
        } while ($exists);

        return $code;
    }

    private function expireIfNeeded(OwnerRegistrationInvite $invite): void
    {
        if ($invite->status !== InviteStatus::PENDING || $invite->expires_at === null || ! $invite->expires_at->isPast()) {
            return;
        }

        $invite->forceFill([
            'status' => InviteStatus::EXPIRED,
        ])->save();
    }

    private function invalidStatusMessage(InviteStatus $status): string
    {
        return match ($status) {
            InviteStatus::USED => 'Kode invite ini sudah dipakai.',
            InviteStatus::EXPIRED => 'Kode invite ini sudah kedaluwarsa.',
            InviteStatus::REVOKED => 'Kode invite ini sudah dicabut.',
            default => 'Kode invite tidak dapat digunakan.',
        };
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function recordPlatformAudit(
        PlatformAdminUser $admin,
        OwnerRegistrationInvite $invite,
        string $action,
        string $reason,
        ?array $before,
        ?array $after,
    ): void {
        DB::table('platform_admin_audit_logs')->insert([
            'platform_admin_user_id' => $admin->id,
            'action' => $action,
            'target_entity_type' => 'owner_registration_invite',
            'target_entity_id' => $invite->id,
            'reason_note' => $reason,
            'before_snapshot' => $before !== null ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_snapshot' => $after !== null ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
