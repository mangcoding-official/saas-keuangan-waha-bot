<?php

namespace App\Services;

use App\Models\OwnerRegistrationInvite;
use App\Services\Waha\WahaClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class OwnerRegistrationInviteWhatsappService
{
    public function __construct(
        private readonly ActiveBotTargetService $activeBotTargetService,
        private readonly WahaClient $wahaClient,
    ) {
    }

    public function send(OwnerRegistrationInvite $invite): bool
    {
        $targetNumber = trim((string) $invite->invited_whatsapp_number_normalized);
        $botTarget = $this->activeBotTargetService->resolve();
        $sessionKey = trim((string) ($botTarget['session_key'] ?? ''));

        if ($targetNumber === '' || $sessionKey === '') {
            Log::warning('Owner registration invite WhatsApp delivery skipped', [
                'invite_id' => $invite->id,
                'reason' => $targetNumber === '' ? 'missing_target' : 'missing_active_bot',
            ]);

            return false;
        }

        try {
            $this->wahaClient->sendText(
                $sessionKey,
                $targetNumber.'@c.us',
                $this->buildMessage($invite)
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('Owner registration invite WhatsApp delivery failed', [
                'invite_id' => $invite->id,
                'session' => $sessionKey,
                'chat_id' => $targetNumber.'@c.us',
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function buildMessage(OwnerRegistrationInvite $invite): string
    {
        $lines = [
            'Halo,',
            'Anda mendapat invite untuk registrasi owner di MACAU Bot.',
            '',
            'Kode invite: '.$invite->code,
            'Link registrasi: '.route('tenant.register.create', ['invite' => $invite->code]),
        ];

        if ($invite->expires_at !== null) {
            $lines[] = 'Berlaku sampai: '.$invite->expires_at->format('d M Y H:i');
        }

        if ($invite->note !== null && trim($invite->note) !== '') {
            $lines[] = 'Catatan: '.trim($invite->note);
        }

        $lines[] = '';
        $lines[] = 'Silakan buka link di atas dan lanjutkan registrasi owner.';

        return implode("\n", $lines);
    }
}
