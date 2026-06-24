<?php

namespace App\Services\Waha;

use App\Enums\WahaConnectionStatus;
use App\Enums\WahaQrStatus;
use App\Models\PlatformAdminUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WahaManagementService
{
    public function __construct(
        private readonly WahaClient $wahaClient,
    ) {
    }

    /**
     * @return array{message:string}
     */
    public function reconnect(PlatformAdminUser $admin, int $botInstanceId, ?string $reasonNote = null): array
    {
        $bot = $this->findBotInstance($botInstanceId);
        $before = $this->snapshot($bot);
        $after = $before;
        $action = 'waha_reconnect_requested';

        try {
            $response = $this->wahaClient->restartSession((string) $bot->waha_instance_key);
            $sessionInfo = $this->safeGetSessionInfo((string) $bot->waha_instance_key);

            $after = $this->updateBotInstance(
                $botInstanceId,
                $before,
                [
                    'connection_status' => $this->resolveConnectionStatus($sessionInfo, WahaConnectionStatus::CONNECTING->value),
                    'qr_status' => $this->resolveQrStatus($sessionInfo, $before['qr_status']),
                    'last_reconnect_at' => now(),
                    'last_error_message' => null,
                    'meta_json' => $this->mergeMeta($before['meta_json'], [
                        'last_reconnect_response' => $response,
                        'last_session_info' => $sessionInfo,
                    ]),
                ],
            );

            $this->recordAudit($admin, $action, $botInstanceId, $reasonNote ?: 'Manual internal WAHA reconnect', $before, $after);

            return [
                'message' => 'Reconnect WAHA diminta untuk '.$bot->name.'.',
            ];
        } catch (Throwable $exception) {
            $after = $this->updateBotInstance(
                $botInstanceId,
                $before,
                [
                    'connection_status' => WahaConnectionStatus::ERROR->value,
                    'last_error_message' => $this->truncateError($exception->getMessage()),
                    'meta_json' => $this->mergeMeta($before['meta_json'], [
                        'last_reconnect_failed_at' => now()->toIso8601String(),
                    ]),
                ],
            );

            $this->recordAudit($admin, $action, $botInstanceId, $reasonNote ?: 'Manual internal WAHA reconnect', $before, $after);

            throw new RuntimeException('Reconnect WAHA gagal: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @return array{message:string, qr_data_url:?string}
     */
    public function refreshQr(PlatformAdminUser $admin, int $botInstanceId, ?string $reasonNote = null): array
    {
        $bot = $this->findBotInstance($botInstanceId);
        $before = $this->snapshot($bot);
        $after = $before;
        $action = 'waha_qr_refresh_requested';

        try {
            $qr = $this->wahaClient->refreshQrCode((string) $bot->waha_instance_key);
            $sessionInfo = $this->safeGetSessionInfo((string) $bot->waha_instance_key);
            $qrStatus = $qr['data_url']
                ? WahaQrStatus::READY->value
                : $this->resolveQrStatus($sessionInfo, WahaQrStatus::NOT_REQUIRED->value);

            $after = $this->updateBotInstance(
                $botInstanceId,
                $before,
                [
                    'connection_status' => $this->resolveConnectionStatus($sessionInfo, $before['connection_status']),
                    'qr_status' => $qrStatus,
                    'last_qr_refresh_at' => now(),
                    'last_error_message' => null,
                    'meta_json' => $this->mergeMeta($before['meta_json'], [
                        'latest_qr' => [
                            'data_url' => $qr['data_url'],
                            'refreshed_at' => now()->toIso8601String(),
                        ],
                        'last_qr_response' => $qr['payload'],
                        'last_session_info' => $sessionInfo,
                    ]),
                ],
            );

            $this->recordAudit($admin, $action, $botInstanceId, $reasonNote ?: 'Manual internal WAHA QR refresh', $before, $after);

            return [
                'message' => $qr['data_url']
                    ? 'QR WAHA berhasil direfresh untuk '.$bot->name.'.'
                    : 'QR WAHA direfresh, tetapi WAHA tidak mengembalikan image baru untuk '.$bot->name.'.',
                'qr_data_url' => $qr['data_url'],
            ];
        } catch (Throwable $exception) {
            $after = $this->updateBotInstance(
                $botInstanceId,
                $before,
                [
                    'qr_status' => WahaQrStatus::UNKNOWN->value,
                    'last_error_message' => $this->truncateError($exception->getMessage()),
                    'meta_json' => $this->mergeMeta($before['meta_json'], [
                        'last_qr_refresh_failed_at' => now()->toIso8601String(),
                    ]),
                ],
            );

            $this->recordAudit($admin, $action, $botInstanceId, $reasonNote ?: 'Manual internal WAHA QR refresh', $before, $after);

            throw new RuntimeException('QR refresh WAHA gagal: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function findBotInstance(int $botInstanceId): object
    {
        $bot = DB::table('bot_instances')->where('id', $botInstanceId)->first();

        if ($bot === null) {
            throw new RuntimeException('Bot instance WAHA tidak ditemukan.');
        }

        return $bot;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $bot): array
    {
        return [
            'id' => (int) $bot->id,
            'name' => (string) $bot->name,
            'waha_instance_key' => (string) $bot->waha_instance_key,
            'connection_status' => (string) $bot->connection_status,
            'qr_status' => (string) $bot->qr_status,
            'webhook_status' => $bot->webhook_status,
            'last_heartbeat_at' => $bot->last_heartbeat_at,
            'last_reconnect_at' => $bot->last_reconnect_at,
            'last_qr_refresh_at' => $bot->last_qr_refresh_at,
            'last_error_message' => $bot->last_error_message,
            'meta_json' => $this->decodeMeta($bot->meta_json),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function updateBotInstance(int $botInstanceId, array $before, array $attributes): array
    {
        $payload = Arr::except($attributes, ['meta_json']);

        if (array_key_exists('meta_json', $attributes)) {
            $payload['meta_json'] = json_encode($attributes['meta_json'], JSON_THROW_ON_ERROR);
        }

        DB::table('bot_instances')
            ->where('id', $botInstanceId)
            ->update($payload);

        return array_merge($before, $attributes);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        PlatformAdminUser $admin,
        string $action,
        int $botInstanceId,
        string $reasonNote,
        array $before,
        array $after,
    ): void {
        DB::table('platform_admin_audit_logs')->insert([
            'platform_admin_user_id' => $admin->id,
            'action' => $action,
            'target_entity_type' => 'bot_instance',
            'target_entity_id' => $botInstanceId,
            'reason_note' => $reasonNote,
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeGetSessionInfo(string $sessionKey): array
    {
        try {
            return $this->wahaClient->getSessionInfo($sessionKey);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $sessionInfo
     */
    private function resolveConnectionStatus(array $sessionInfo, string $fallback): string
    {
        $status = strtoupper((string) ($sessionInfo['status'] ?? $sessionInfo['session']['status'] ?? ''));

        return match ($status) {
            'WORKING' => WahaConnectionStatus::CONNECTED->value,
            'STARTING', 'SCAN_QR_CODE' => WahaConnectionStatus::CONNECTING->value,
            'FAILED', 'STOPPED' => WahaConnectionStatus::ERROR->value,
            default => $fallback,
        };
    }

    /**
     * @param  array<string, mixed>  $sessionInfo
     */
    private function resolveQrStatus(array $sessionInfo, string $fallback): string
    {
        $status = strtoupper((string) ($sessionInfo['status'] ?? $sessionInfo['session']['status'] ?? ''));

        return match ($status) {
            'WORKING' => WahaQrStatus::NOT_REQUIRED->value,
            'SCAN_QR_CODE' => WahaQrStatus::READY->value,
            'FAILED' => WahaQrStatus::EXPIRED->value,
            default => $fallback,
        };
    }

    /**
     * @param  mixed  $rawMeta
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $rawMeta): array
    {
        if (is_array($rawMeta)) {
            return $rawMeta;
        }

        if (! is_string($rawMeta) || trim($rawMeta) === '') {
            return [];
        }

        $decoded = json_decode($rawMeta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function mergeMeta(array $current, array $extra): array
    {
        return array_merge($current, $extra);
    }

    private function truncateError(string $message): string
    {
        return Str::limit(trim($message), 1000, '...');
    }
}
