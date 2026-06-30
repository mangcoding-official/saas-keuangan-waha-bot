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
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{tenant: Tenant, owner: TenantUser, activation_code: string, activation_expires_at: string, whatsapp_sent: bool}
     */
    public function register(array $payload): array
    {
        $normalizedNumber = $this->normalizeOwnerNumber((string) $payload['owner_whatsapp']);

        return DB::transaction(function () use ($payload, $normalizedNumber): array {
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
                'email' => mb_strtolower(trim((string) $payload['owner_email'])),
                'password' => Hash::make((string) $payload['owner_password']),
                'role' => UserRole::OWNER,
                'user_status' => UserStatus::ACTIVE,
                'whatsapp_number' => trim((string) $payload['owner_whatsapp']),
                'whatsapp_number_normalized' => $normalizedNumber,
                'verification_status' => VerificationStatus::PENDING_VERIFICATION,
            ]);

            $activationCode = $this->activationCodeService->issue($owner->id, 'owner_created');
            $activationExpiresAt = now()->addMinutes((int) config('platform.timeouts.activation_code_minutes'))->toIso8601String();

            $this->createDefaultAccountFor($tenant->id);
            $this->categoryTemplateService->createDefaultsForTenant($tenant->id, TenantType::from((string) $payload['tenant_type']));

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'activation_code' => $activationCode,
                'activation_expires_at' => $activationExpiresAt,
                'whatsapp_sent' => $this->activationCodeDeliveryService->send($owner, $activationCode, 'owner_created'),
            ];
        });
    }

    private function normalizeOwnerNumber(string $rawNumber): string
    {
        try {
            $normalized = $this->phoneNumberNormalizer->normalize($rawNumber);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'owner_whatsapp' => $exception->getMessage(),
            ]);
        }

        $userExists = DB::table('tenant_users')
            ->where('whatsapp_number_normalized', $normalized)
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

        return $normalized;
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
}
