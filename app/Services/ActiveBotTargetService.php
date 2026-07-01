<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ActiveBotTargetService
{
    /**
     * @return array{session_key?: string, display_number?: string, wa_number?: string}
     */
    public function resolve(): array
    {
        $bot = DB::table('bot_instances')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first([
                'waha_instance_key',
                'bot_whatsapp_number',
                'bot_whatsapp_number_normalized',
            ]);

        if (! $bot) {
            return [];
        }

        $sessionKey = trim((string) $bot->waha_instance_key);
        $waNumber = trim((string) $bot->bot_whatsapp_number_normalized);
        $displayNumber = trim((string) ($bot->bot_whatsapp_number ?: $bot->bot_whatsapp_number_normalized));

        return array_filter([
            'session_key' => $sessionKey !== '' ? $sessionKey : null,
            'display_number' => $displayNumber !== '' ? $displayNumber : null,
            'wa_number' => $waNumber !== '' ? $waNumber : null,
        ], static fn (?string $value): bool => $value !== null);
    }
}
