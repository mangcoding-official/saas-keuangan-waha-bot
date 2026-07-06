<?php

namespace App\Services;

use App\Models\TenantPasswordResetToken;
use App\Models\TenantUser;
use App\Services\Waha\WahaClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantPasswordResetWhatsappService
{
    public function __construct(
        private readonly ActiveBotTargetService $activeBotTargetService,
        private readonly WahaClient $wahaClient,
    ) {
    }

    public function send(TenantPasswordResetToken $resetToken, TenantUser $targetUser, string $plainToken): bool
    {
        $targetNumber = trim((string) $targetUser->whatsapp_number_normalized);
        $botTarget = $this->activeBotTargetService->resolve();
        $sessionKey = trim((string) ($botTarget['session_key'] ?? ''));

        if ($targetNumber === '' || $sessionKey === '') {
            Log::warning('Tenant password reset WhatsApp delivery skipped', [
                'tenant_password_reset_token_id' => $resetToken->id,
                'tenant_user_id' => $targetUser->id,
                'reason' => $targetNumber === '' ? 'missing_target' : 'missing_active_bot',
            ]);

            return false;
        }

        try {
            $this->wahaClient->sendText(
                $sessionKey,
                $targetNumber.'@c.us',
                $this->buildMessage($resetToken, $targetUser, $plainToken)
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('Tenant password reset WhatsApp delivery failed', [
                'tenant_password_reset_token_id' => $resetToken->id,
                'tenant_user_id' => $targetUser->id,
                'session' => $sessionKey,
                'chat_id' => $targetNumber.'@c.us',
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function buildMessage(TenantPasswordResetToken $resetToken, TenantUser $targetUser, string $plainToken): string
    {
        $tenantTimezone = $targetUser->tenant?->timezone ?: config('app.timezone');
        $resetUrl = route('tenant.password.reset.edit', [
            'lookup' => $resetToken->lookup_key,
            'token' => $plainToken,
        ]);

        return trim(implode("\n", [
            'Halo '.$targetUser->name.',',
            'Kami menerima permintaan reset password akun MACAU Anda.',
            '',
            'Link reset password: '.$resetUrl,
            'Berlaku sampai: '.$resetToken->expires_at?->timezone($tenantTimezone)->format('d M Y H:i'),
            '',
            'Jika Anda tidak meminta reset password, abaikan pesan ini.',
        ]));
    }
}
