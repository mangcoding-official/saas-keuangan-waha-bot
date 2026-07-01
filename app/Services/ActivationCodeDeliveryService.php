<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Services\Waha\WahaClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivationCodeDeliveryService
{
    public function __construct(
        private readonly ActiveBotTargetService $activeBotTargetService,
        private readonly WahaClient $wahaClient,
    ) {
    }

    public function send(TenantUser $targetUser, string $plainCode, string $context = 'activation_code'): bool
    {
        $sessionKey = $this->resolveSessionKey();
        $chatId = trim($targetUser->whatsapp_number_normalized).'@c.us';

        if ($sessionKey === null || trim($targetUser->whatsapp_number_normalized) === '') {
            Log::warning('Activation code delivery skipped', [
                'tenant_user_id' => $targetUser->id,
                'context' => $context,
                'reason' => 'missing_session_or_target',
            ]);

            return false;
        }

        try {
            $this->wahaClient->sendText($sessionKey, $chatId, $this->buildMessage($targetUser, $plainCode, $context));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Activation code delivery failed', [
                'tenant_user_id' => $targetUser->id,
                'context' => $context,
                'session' => $sessionKey,
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveSessionKey(): ?string
    {
        $botTarget = $this->activeBotTargetService->resolve();
        $sessionKey = trim((string) (($botTarget['session_key'] ?? null) ?: config('services.waha.default_session')));

        return $sessionKey !== '' ? $sessionKey : null;
    }

    private function buildMessage(TenantUser $targetUser, string $plainCode, string $context): string
    {
        $intro = match ($context) {
            'owner_created' => 'Registrasi berhasil. Berikut kode aktivasi owner kamu.',
            'member_created' => 'Kamu ditambahkan ke tenant baru. Berikut kode aktivasi akun kamu.',
            'resent', 'resent_by_admin' => 'Berikut activation code terbaru untuk akun kamu.',
            'regenerated', 'regenerated_by_admin' => 'Activation code lama dibatalkan. Berikut code baru untuk akun kamu.',
            default => 'Berikut activation code akun kamu.',
        };

        return trim(implode("\n", [
            'Halo '.$targetUser->name.',',
            $intro,
            '',
            'Kode: '.$plainCode,
            'Kirim balasan: AKTIF '.$plainCode,
            'Atau: AKTIV '.$plainCode,
            '',
            'Code berlaku '.config('platform.timeouts.activation_code_minutes').' menit.',
        ]));
    }
}
