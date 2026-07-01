<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\AiAddonStatus;
use App\Enums\ServicePlan;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantOwnerRegistrationService
{
    public function __construct(
        private readonly ActivationCodeService $activationCodeService,
        private readonly ActivationCodeDeliveryService $activationCodeDeliveryService,
        private readonly CategoryTemplateService $categoryTemplateService,
        private readonly OwnerRegistrationInviteService $ownerRegistrationInviteService,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{tenant: Tenant, owner: TenantUser, activation_code: string, activation_expires_at: string, whatsapp_sent: bool}
     */
    public function register(array $payload): array
    {
        $normalizedEmail = mb_strtolower(trim((string) $payload['owner_email']));
        $normalizedNumber = $this->normalizePhoneNumber((string) $payload['owner_whatsapp']);
        $existingOwner = $this->resolveReusableOwner($normalizedEmail, $normalizedNumber);
        $this->assertNumberAllowed($normalizedNumber, $existingOwner?->id);

        return DB::transaction(function () use ($payload, $normalizedEmail, $normalizedNumber, $existingOwner): array {
            if ($existingOwner !== null) {
                $tenant = Tenant::query()->findOrFail($existingOwner->tenant_id);
                $tenantType = TenantType::from((string) $payload['tenant_type']);

                $tenant->forceFill([
                    'name' => trim((string) $payload['tenant_name']),
                    'tenant_type' => $tenantType,
                    'timezone' => (string) $payload['timezone'],
                ])->save();

                $owner = $existingOwner->forceFill([
                    'name' => trim((string) $payload['owner_name']),
                    'email' => $normalizedEmail,
                    'password' => Hash::make((string) $payload['owner_password']),
                    'role' => UserRole::OWNER,
                    'user_status' => UserStatus::ACTIVE,
                    'whatsapp_number' => trim((string) $payload['owner_whatsapp']),
                    'whatsapp_number_normalized' => $normalizedNumber,
                    'verification_status' => VerificationStatus::PENDING_VERIFICATION,
                    'verified_at' => null,
                ]);
                $owner->save();

                $this->ensureDefaultAccountFor($tenant->id);
                $this->categoryTemplateService->ensureDefaultsForTenant($tenant->id, $tenantType);
            } else {
                $tenant = Tenant::query()->create([
                    'name' => trim((string) $payload['tenant_name']),
                    'tenant_type' => TenantType::from((string) $payload['tenant_type']),
                    'timezone' => (string) $payload['timezone'],
                    'tenant_status' => TenantStatus::ACTIVE,
                    'service_plan' => ServicePlan::ALPHA,
                    'service_status' => ServiceStatus::ACTIVE,
                    'ai_addon_status' => AiAddonStatus::INACTIVE,
                ]);

                $owner = TenantUser::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => trim((string) $payload['owner_name']),
                    'email' => $normalizedEmail,
                    'password' => Hash::make((string) $payload['owner_password']),
                    'role' => UserRole::OWNER,
                    'user_status' => UserStatus::ACTIVE,
                    'whatsapp_number' => trim((string) $payload['owner_whatsapp']),
                    'whatsapp_number_normalized' => $normalizedNumber,
                    'verification_status' => VerificationStatus::PENDING_VERIFICATION,
                ]);

                $this->createDefaultAccountFor($tenant->id);
                $this->categoryTemplateService->createDefaultsForTenant($tenant->id, TenantType::from((string) $payload['tenant_type']));
            }

            $activationCode = $this->activationCodeService->issue($owner->id, $existingOwner !== null ? 'owner_reinvited' : 'owner_created');
            $activationExpiresAt = now()->addMinutes((int) config('platform.timeouts.activation_code_minutes'))->toIso8601String();

            $this->ownerRegistrationInviteService->consumeForOwnerRegistration(
                rawCode: (string) $payload['invite_code'],
                ownerEmail: $normalizedEmail,
                tenant: $tenant,
                owner: $owner,
            );

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'activation_code' => $activationCode,
                'activation_expires_at' => $activationExpiresAt,
                'whatsapp_sent' => $this->activationCodeDeliveryService->send($owner, $activationCode, 'owner_created'),
            ];
        });
    }

    private function normalizePhoneNumber(string $rawNumber): string
    {
        try {
            return $this->phoneNumberNormalizer->normalize($rawNumber);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'owner_whatsapp' => $exception->getMessage(),
            ]);
        }
    }

    private function assertNumberAllowed(string $normalized, ?int $ignoreTenantUserId = null): void
    {
        $userExists = DB::table('tenant_users')
            ->where('whatsapp_number_normalized', $normalized)
            ->when($ignoreTenantUserId !== null, fn ($query) => $query->where('id', '!=', $ignoreTenantUserId))
            ->exists();

        if ($userExists) {
            throw ValidationException::withMessages([
                'owner_whatsapp' => 'Nomor WhatsApp ini sudah dipakai tenant lain.',
            ]);
        }

        $botNumberExists = DB::table('bot_instances')
            ->where('is_active', true)
            ->where('bot_whatsapp_number_normalized', $normalized)
            ->exists();

        if ($botNumberExists) {
            throw ValidationException::withMessages([
                'owner_whatsapp' => 'Nomor WhatsApp owner tidak boleh sama dengan nomor bot aktif.',
            ]);
        }
    }

    private function createDefaultAccountFor(int $tenantId): void
    {
        DB::table('accounts')->insert([
            'tenant_id' => $tenantId,
            'name' => 'Cash',
            'account_type' => AccountType::CASH->value,
            'is_default' => true,
            'opening_balance' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureDefaultAccountFor(int $tenantId): void
    {
        $exists = DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->exists();

        if (! $exists) {
            $this->createDefaultAccountFor($tenantId);
        }
    }

    private function resolveReusableOwner(string $normalizedEmail, string $normalizedNumber): ?TenantUser
    {
        /** @var TenantUser|null $matchedByEmail */
        $matchedByEmail = TenantUser::query()
            ->where('email', $normalizedEmail)
            ->first();

        /** @var TenantUser|null $matchedByNumber */
        $matchedByNumber = TenantUser::query()
            ->where('whatsapp_number_normalized', $normalizedNumber)
            ->first();

        if ($matchedByEmail !== null && $matchedByNumber !== null && $matchedByEmail->id !== $matchedByNumber->id) {
            throw ValidationException::withMessages([
                'owner_email' => 'Email dan nomor WhatsApp sudah terhubung ke akun owner yang berbeda.',
                'owner_whatsapp' => 'Email dan nomor WhatsApp sudah terhubung ke akun owner yang berbeda.',
            ]);
        }

        $owner = $matchedByEmail ?? $matchedByNumber;

        if ($owner === null) {
            return null;
        }

        if ($owner->role !== UserRole::OWNER) {
            throw ValidationException::withMessages([
                'owner_email' => 'Email owner ini sudah dipakai user lain.',
                'owner_whatsapp' => 'Nomor WhatsApp ini sudah dipakai user lain.',
            ]);
        }

        if ($owner->verification_status !== VerificationStatus::PENDING_VERIFICATION) {
            throw ValidationException::withMessages([
                'owner_email' => 'Email owner ini sudah terdaftar dan aktif.',
                'owner_whatsapp' => 'Nomor WhatsApp ini sudah terdaftar dan aktif.',
            ]);
        }

        return $owner;
    }
}
