<?php

namespace App\Services;

use App\Enums\PasswordResetTokenStatus;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\TenantPasswordResetToken;
use App\Models\TenantUser;
use App\Support\PhoneNumberNormalizer;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantPasswordResetService
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly TenantPasswordResetWhatsappService $tenantPasswordResetWhatsappService,
    ) {
    }

    public function requestResetLink(string $rawWhatsappNumber, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        try {
            $normalizedNumber = $this->phoneNumberNormalizer->normalize($rawWhatsappNumber);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'whatsapp_number' => $exception->getMessage(),
            ]);
        }

        $rateLimitReason = $this->rateLimitReason($normalizedNumber, $ipAddress);

        if ($rateLimitReason !== null) {
            $this->recordAttempt(
                rawWhatsappNumber: $rawWhatsappNumber,
                normalizedWhatsappNumber: $normalizedNumber,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                status: PasswordResetTokenStatus::RATE_LIMITED,
                failureReason: $rateLimitReason,
            );

            return;
        }

        $this->hitRateLimit($normalizedNumber, $ipAddress);

        ['user' => $tenantUser, 'failure_reason' => $failureReason] = $this->resolveResetCandidate($normalizedNumber);

        if (! ($tenantUser instanceof TenantUser)) {
            $this->recordAttempt(
                rawWhatsappNumber: $rawWhatsappNumber,
                normalizedWhatsappNumber: $normalizedNumber,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                status: PasswordResetTokenStatus::REJECTED,
                failureReason: $failureReason ?? 'unknown_user',
            );

            return;
        }

        if ($failureReason !== null) {
            $this->recordAttempt(
                rawWhatsappNumber: $rawWhatsappNumber,
                normalizedWhatsappNumber: $normalizedNumber,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                status: PasswordResetTokenStatus::REJECTED,
                failureReason: $failureReason,
                tenantUser: $tenantUser,
            );

            return;
        }

        $plainToken = Str::random(64);
        $expiresAt = now()->addMinutes($this->expiryMinutes());

        $resetToken = TenantPasswordResetToken::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'lookup_key' => (string) Str::ulid(),
            'token_hash' => Hash::make($plainToken),
            'status' => PasswordResetTokenStatus::PENDING,
            'requested_whatsapp_number' => trim($rawWhatsappNumber),
            'requested_whatsapp_number_normalized' => $normalizedNumber,
            'requested_ip_address' => $this->trimNullable($ipAddress, 45),
            'requested_user_agent' => $this->trimNullable($userAgent, 255),
            'delivery_channel' => 'waha',
            'requested_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $sent = $this->tenantPasswordResetWhatsappService->send($resetToken->fresh(['tenantUser.tenant']), $tenantUser, $plainToken);

        if (! $sent) {
            $resetToken->forceFill([
                'status' => PasswordResetTokenStatus::DELIVERY_FAILED,
                'failure_reason' => 'delivery_failed',
                'invalidated_at' => now(),
            ])->save();

            return;
        }

        DB::transaction(function () use ($resetToken): void {
            TenantPasswordResetToken::query()
                ->where('tenant_user_id', $resetToken->tenant_user_id)
                ->where('id', '!=', $resetToken->id)
                ->where('status', PasswordResetTokenStatus::SENT->value)
                ->update([
                    'status' => PasswordResetTokenStatus::INVALIDATED->value,
                    'failure_reason' => 'reissued',
                    'invalidated_at' => now(),
                    'updated_at' => now(),
                ]);

            $lockedToken = TenantPasswordResetToken::query()
                ->whereKey($resetToken->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedToken->forceFill([
                'status' => PasswordResetTokenStatus::SENT,
                'sent_at' => now(),
                'failure_reason' => null,
            ])->save();
        });
    }

    /**
     * @return array{
     *     is_valid: bool,
     *     message: string,
     *     token: ?TenantPasswordResetToken
     * }
     */
    public function resolveResetLink(string $lookupKey, ?string $plainToken): array
    {
        $resetToken = TenantPasswordResetToken::query()
            ->with(['tenantUser.tenant'])
            ->where('lookup_key', trim($lookupKey))
            ->first();

        if (! ($resetToken instanceof TenantPasswordResetToken) || trim((string) $plainToken) === '') {
            return [
                'is_valid' => false,
                'message' => 'Link reset password tidak valid atau sudah kedaluwarsa.',
                'token' => null,
            ];
        }

        $this->expireIfNeeded($resetToken);

        if ($resetToken->status !== PasswordResetTokenStatus::SENT) {
            return [
                'is_valid' => false,
                'message' => 'Link reset password tidak valid atau sudah kedaluwarsa.',
                'token' => $resetToken,
            ];
        }

        if (! Hash::check((string) $plainToken, (string) $resetToken->token_hash)) {
            return [
                'is_valid' => false,
                'message' => 'Link reset password tidak valid atau sudah kedaluwarsa.',
                'token' => $resetToken,
            ];
        }

        if (! $this->isCurrentlyEligible($resetToken->tenantUser)) {
            return [
                'is_valid' => false,
                'message' => 'Akun ini sedang tidak bisa memproses reset password. Minta link baru saat akun aktif kembali.',
                'token' => $resetToken,
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Silakan buat password baru untuk akun Anda.',
            'token' => $resetToken,
        ];
    }

    public function resetPassword(
        string $lookupKey,
        string $plainToken,
        string $newPassword,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        DB::transaction(function () use ($lookupKey, $plainToken, $newPassword, $ipAddress, $userAgent): void {
            /** @var TenantPasswordResetToken|null $resetToken */
            $resetToken = TenantPasswordResetToken::query()
                ->with(['tenantUser.tenant'])
                ->where('lookup_key', trim($lookupKey))
                ->lockForUpdate()
                ->first();

            if (! ($resetToken instanceof TenantPasswordResetToken)) {
                throw $this->invalidLinkException();
            }

            $this->expireIfNeeded($resetToken);

            if ($resetToken->status !== PasswordResetTokenStatus::SENT) {
                throw $this->invalidLinkException();
            }

            if (! Hash::check(trim($plainToken), (string) $resetToken->token_hash)) {
                throw $this->invalidLinkException();
            }

            $tenantUser = $resetToken->tenantUser;

            if (! ($tenantUser instanceof TenantUser) || ! $this->isCurrentlyEligible($tenantUser)) {
                $resetToken->forceFill([
                    'status' => PasswordResetTokenStatus::INVALIDATED,
                    'failure_reason' => 'user_no_longer_eligible',
                    'invalidated_at' => now(),
                ])->save();

                throw ValidationException::withMessages([
                    'password' => 'Akun ini sedang tidak bisa memproses reset password. Minta link baru saat akun aktif kembali.',
                ]);
            }

            $tenantUser->forceFill([
                'password' => $newPassword,
            ])->save();

            $resetToken->forceFill([
                'status' => PasswordResetTokenStatus::USED,
                'used_at' => now(),
                'consumed_ip_address' => $this->trimNullable($ipAddress, 45),
                'consumed_user_agent' => $this->trimNullable($userAgent, 255),
            ])->save();
        });
    }

    /**
     * @return array{user:?TenantUser,failure_reason:?string}
     */
    private function resolveResetCandidate(string $normalizedWhatsappNumber): array
    {
        /** @var TenantUser|null $tenantUser */
        $tenantUser = TenantUser::query()
            ->with('tenant')
            ->where('whatsapp_number_normalized', $normalizedWhatsappNumber)
            ->first();

        if (! ($tenantUser instanceof TenantUser)) {
            return [
                'user' => null,
                'failure_reason' => 'unknown_user',
            ];
        }

        if ($tenantUser->tenant === null) {
            return [
                'user' => $tenantUser,
                'failure_reason' => 'tenant_missing',
            ];
        }

        if ($tenantUser->tenant->tenant_status !== TenantStatus::ACTIVE) {
            return [
                'user' => $tenantUser,
                'failure_reason' => 'tenant_inactive',
            ];
        }

        if ($tenantUser->tenant->service_status !== ServiceStatus::ACTIVE) {
            return [
                'user' => $tenantUser,
                'failure_reason' => 'service_inactive',
            ];
        }

        if ($tenantUser->user_status !== UserStatus::ACTIVE) {
            return [
                'user' => $tenantUser,
                'failure_reason' => 'user_inactive',
            ];
        }

        if ($tenantUser->verification_status !== VerificationStatus::VERIFIED) {
            return [
                'user' => $tenantUser,
                'failure_reason' => 'user_not_verified',
            ];
        }

        return [
            'user' => $tenantUser,
            'failure_reason' => null,
        ];
    }

    private function isCurrentlyEligible(?TenantUser $tenantUser): bool
    {
        if (! ($tenantUser instanceof TenantUser) || $tenantUser->tenant === null) {
            return false;
        }

        return $tenantUser->tenant->tenant_status === TenantStatus::ACTIVE
            && $tenantUser->tenant->service_status === ServiceStatus::ACTIVE
            && $tenantUser->user_status === UserStatus::ACTIVE
            && $tenantUser->verification_status === VerificationStatus::VERIFIED;
    }

    private function expireIfNeeded(TenantPasswordResetToken $resetToken): void
    {
        if ($resetToken->status !== PasswordResetTokenStatus::SENT || ! ($resetToken->expires_at instanceof Carbon) || ! $resetToken->expires_at->isPast()) {
            return;
        }

        $resetToken->forceFill([
            'status' => PasswordResetTokenStatus::EXPIRED,
            'failure_reason' => 'expired',
            'expired_at' => now(),
        ])->save();
    }

    private function recordAttempt(
        string $rawWhatsappNumber,
        string $normalizedWhatsappNumber,
        ?string $ipAddress,
        ?string $userAgent,
        PasswordResetTokenStatus $status,
        string $failureReason,
        ?TenantUser $tenantUser = null,
    ): TenantPasswordResetToken {
        return TenantPasswordResetToken::query()->create([
            'tenant_id' => $tenantUser?->tenant_id,
            'tenant_user_id' => $tenantUser?->id,
            'status' => $status,
            'requested_whatsapp_number' => trim($rawWhatsappNumber),
            'requested_whatsapp_number_normalized' => $normalizedWhatsappNumber,
            'requested_ip_address' => $this->trimNullable($ipAddress, 45),
            'requested_user_agent' => $this->trimNullable($userAgent, 255),
            'failure_reason' => $failureReason,
            'requested_at' => now(),
        ]);
    }

    private function rateLimitReason(string $normalizedWhatsappNumber, ?string $ipAddress): ?string
    {
        if (RateLimiter::tooManyAttempts($this->numberRateLimitKey($normalizedWhatsappNumber), $this->maxAttempts())) {
            return 'number_rate_limited';
        }

        if (RateLimiter::tooManyAttempts($this->ipRateLimitKey($ipAddress), $this->maxAttempts())) {
            return 'ip_rate_limited';
        }

        return null;
    }

    private function hitRateLimit(string $normalizedWhatsappNumber, ?string $ipAddress): void
    {
        RateLimiter::hit($this->numberRateLimitKey($normalizedWhatsappNumber), $this->decaySeconds());
        RateLimiter::hit($this->ipRateLimitKey($ipAddress), $this->decaySeconds());
    }

    private function numberRateLimitKey(string $normalizedWhatsappNumber): string
    {
        return 'tenant-password-reset:number:'.sha1($normalizedWhatsappNumber);
    }

    private function ipRateLimitKey(?string $ipAddress): string
    {
        return 'tenant-password-reset:ip:'.sha1(trim((string) ($ipAddress ?: 'unknown')));
    }

    private function expiryMinutes(): int
    {
        return (int) config('platform.password_resets.expire_minutes', 20);
    }

    private function maxAttempts(): int
    {
        return (int) config('platform.password_resets.max_attempts', 3);
    }

    private function decaySeconds(): int
    {
        return (int) config('platform.password_resets.decay_minutes', 15) * 60;
    }

    private function trimNullable(?string $value, int $limit): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $limit, '');
    }

    private function invalidLinkException(): ValidationException
    {
        return ValidationException::withMessages([
            'password' => 'Link reset password tidak valid atau sudah kedaluwarsa.',
        ]);
    }
}
