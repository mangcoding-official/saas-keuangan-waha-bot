<?php

namespace App\Services\Waha;

use App\Enums\ActivationCodeStatus;
use App\Enums\VerificationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class WahaActivationService
{
    /**
     * @return array{route: string, reply_text: string, side_effects: array<int, string>}
     */
    public function attempt(int $tenantUserId, ?string $messageText): array
    {
        $code = $this->extractActivationCode($messageText);

        if ($code === null) {
            return [
                'route' => 'activation_failed',
                'reply_text' => 'Format aktivasi tidak valid. Gunakan: AKTIF KODE.',
                'side_effects' => ['activation_rejected'],
            ];
        }

        $activationCode = DB::table('activation_codes')
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', ActivationCodeStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->first();

        if (! $activationCode) {
            return [
                'route' => 'activation_failed',
                'reply_text' => 'Kode aktivasi tidak tersedia atau sudah tidak berlaku. Minta owner kirim ulang code baru.',
                'side_effects' => ['activation_not_found'],
            ];
        }

        if (now()->greaterThan($activationCode->expires_at)) {
            DB::table('activation_codes')
                ->where('id', $activationCode->id)
                ->update([
                    'status' => ActivationCodeStatus::EXPIRED->value,
                    'active_lock' => null,
                    'invalidated_at' => now(),
                    'invalidated_reason' => 'expired_on_attempt',
                    'updated_at' => now(),
                ]);

            return [
                'route' => 'activation_failed',
                'reply_text' => 'Kode aktivasi sudah expired. Minta owner melakukan regenerate code baru.',
                'side_effects' => ['activation_expired'],
            ];
        }

        try {
            $matches = Hash::check($code, $activationCode->code_hash);
        } catch (RuntimeException) {
            return [
                'route' => 'activation_failed',
                'reply_text' => 'Kode aktivasi saat ini tidak valid. Minta owner melakukan regenerate code baru.',
                'side_effects' => ['activation_hash_invalid'],
            ];
        }

        if (! $matches) {
            return [
                'route' => 'activation_failed',
                'reply_text' => 'Kode aktivasi tidak cocok. Cek lagi code terbaru dari owner lalu kirim ulang.',
                'side_effects' => ['activation_hash_mismatch'],
            ];
        }

        DB::transaction(function () use ($tenantUserId, $activationCode): void {
            DB::table('activation_codes')
                ->where('id', $activationCode->id)
                ->update([
                    'status' => ActivationCodeStatus::USED->value,
                    'active_lock' => null,
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('tenant_users')
                ->where('id', $tenantUserId)
                ->update([
                    'verification_status' => VerificationStatus::VERIFIED->value,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return [
            'route' => 'activation_success',
            'reply_text' => 'Nomor WhatsApp kamu sudah aktif. Sekarang pesan transaksi bisa diproses.',
            'side_effects' => ['activation_code_used', 'tenant_user_verified'],
        ];
    }

    private function extractActivationCode(?string $messageText): ?string
    {
        if (preg_match('/^\s*akti[fv]\s+(\S+)\s*$/i', (string) $messageText, $matches) !== 1) {
            return null;
        }

        return strtoupper(trim($matches[1]));
    }
}
