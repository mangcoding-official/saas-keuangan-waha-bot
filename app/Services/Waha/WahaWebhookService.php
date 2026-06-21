<?php

namespace App\Services\Waha;

use App\Enums\IncomingMessageAccessDecision;
use App\Enums\IncomingMessageIgnoredReason;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class WahaWebhookService
{
    public function __construct(
        private readonly WahaWebhookPayloadNormalizer $payloadNormalizer,
        private readonly WahaAccessGateService $accessGateService,
        private readonly WahaActivationService $activationService,
        private readonly WahaClient $wahaClient,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     status: string,
     *     route: string,
     *     should_reply: bool,
     *     reply_text: ?string,
     *     side_effects: array<int, string>
     * }
     */
    public function handle(array $payload): array
    {
        try {
            $message = $this->payloadNormalizer->normalize($payload);
        } catch (InvalidArgumentException $exception) {
            return [
                'status' => 'ignored',
                'route' => 'ignored',
                'should_reply' => false,
                'reply_text' => null,
                'side_effects' => ['invalid_payload'],
            ];
        }

        $existing = DB::table('incoming_messages')
            ->where('source_message_id', $message['source_message_id'])
            ->first(['id', 'processed_at']);

        if ($existing && $existing->processed_at !== null) {
            return [
                'status' => 'ok',
                'route' => 'duplicate_ignored',
                'should_reply' => false,
                'reply_text' => null,
                'side_effects' => ['duplicate_ignored'],
            ];
        }

        $botInstance = $this->resolveBotInstance($message['bot_instance_key']);
        $incomingMessageId = $existing?->id ?? $this->createPendingIncomingMessage($message, $botInstance?->id);
        $access = $this->accessGateService->evaluate($message);
        $replyText = null;
        $sideEffects = ['incoming_message_logged'];

        if ($access['route'] === 'activation_attempt' && $access['tenant_user']) {
            $activation = $this->activationService->attempt((int) $access['tenant_user']->id, $message['message_text']);
            $replyText = $activation['reply_text'];
            $sideEffects = array_merge($sideEffects, $activation['side_effects']);
            $route = $activation['route'];
            $accessDecision = IncomingMessageAccessDecision::ACCEPTED->value;
            $ignoredReason = null;
            $shouldReply = true;
        } else {
            $route = $access['route'];
            $accessDecision = $access['access_decision'];
            $ignoredReason = $access['ignored_reason'];
            $shouldReply = $access['should_reply'];

            if ($route === 'access_granted') {
                $sideEffects[] = 'access_gate_passed';
            }
        }

        $this->updateIncomingMessage(
            $incomingMessageId,
            $message,
            $botInstance?->id,
            $access['tenant_id'],
            $access['tenant_user']?->id ?? null,
            $accessDecision,
            $ignoredReason,
            $route === 'ignored' ? null : $message['message_text'],
        );

        if ($botInstance) {
            $this->markWebhookHealthy((int) $botInstance->id, $message['raw_payload']);
            $sideEffects[] = 'bot_webhook_healthy';
        }

        if ($shouldReply && $replyText !== null) {
            $this->attemptReply($message, $botInstance?->waha_instance_key, $replyText, $sideEffects);
        }

        Log::info('WAHA webhook processed', [
            'source_message_id' => $message['source_message_id'],
            'route' => $route,
            'access_decision' => $accessDecision,
            'ignored_reason' => $ignoredReason,
        ]);

        return [
            'status' => 'ok',
            'route' => $route,
            'should_reply' => $shouldReply,
            'reply_text' => $replyText,
            'side_effects' => array_values(array_unique($sideEffects)),
        ];
    }

    private function resolveBotInstance(string $sessionKey): ?object
    {
        $session = trim($sessionKey);

        if ($session !== '') {
            $botInstance = DB::table('bot_instances')
                ->where('waha_instance_key', $session)
                ->first(['id', 'waha_instance_key']);

            if ($botInstance) {
                return $botInstance;
            }
        }

        return DB::table('bot_instances')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first(['id', 'waha_instance_key']);
    }

    private function createPendingIncomingMessage(array $message, ?int $botInstanceId): int
    {
        try {
            return (int) DB::table('incoming_messages')->insertGetId([
                'bot_instance_id' => $botInstanceId,
                'tenant_id' => null,
                'tenant_user_id' => null,
                'source_message_id' => $message['source_message_id'],
                'sender_masked' => $this->maskSender($message['sender_normalized']),
                'sender_hash' => $this->hashSender($message['sender_normalized']),
                'sender_normalized' => $message['sender_normalized'],
                'chat_type' => $message['chat_type'],
                'is_from_me' => $message['from_me'],
                'access_decision' => IncomingMessageAccessDecision::IGNORED->value,
                'ignored_reason' => IncomingMessageIgnoredReason::OTHER->value,
                'message_text' => null,
                'payload_json' => json_encode($message['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'received_at' => $message['message_timestamp'],
                'processed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existingId = DB::table('incoming_messages')
                ->where('source_message_id', $message['source_message_id'])
                ->value('id');

            if ($existingId) {
                return (int) $existingId;
            }

            throw $exception;
        }
    }

    private function updateIncomingMessage(
        int $incomingMessageId,
        array $message,
        ?int $botInstanceId,
        ?int $tenantId,
        ?int $tenantUserId,
        string $accessDecision,
        ?string $ignoredReason,
        ?string $messageText,
    ): void {
        DB::table('incoming_messages')
            ->where('id', $incomingMessageId)
            ->update([
                'bot_instance_id' => $botInstanceId,
                'tenant_id' => $tenantId,
                'tenant_user_id' => $tenantUserId,
                'sender_masked' => $this->maskSender($message['sender_normalized']),
                'sender_hash' => $this->hashSender($message['sender_normalized']),
                'sender_normalized' => $message['sender_normalized'],
                'chat_type' => $message['chat_type'],
                'is_from_me' => $message['from_me'],
                'access_decision' => $accessDecision,
                'ignored_reason' => $ignoredReason,
                'message_text' => $messageText,
                'payload_json' => json_encode($message['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'received_at' => $message['message_timestamp'],
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, string>  $sideEffects
     */
    private function attemptReply(array $message, ?string $resolvedSession, string $replyText, array &$sideEffects): void
    {
        $chatId = $message['chat_id'];

        if ($chatId === null) {
            $sideEffects[] = 'reply_skipped_no_chat_id';

            return;
        }

        $session = trim((string) ($resolvedSession ?: $message['bot_instance_key']));

        try {
            $this->wahaClient->sendText($session, $chatId, $replyText);
            $sideEffects[] = 'reply_sent';
        } catch (Throwable $exception) {
            Log::warning('WAHA reply failed', [
                'source_message_id' => $message['source_message_id'],
                'session' => $session,
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            $sideEffects[] = 'reply_send_failed';
        }
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function markWebhookHealthy(int $botInstanceId, array $rawPayload): void
    {
        $botNumber = $this->extractBotNumber($rawPayload);

        DB::table('bot_instances')
            ->where('id', $botInstanceId)
            ->update([
                'bot_whatsapp_number' => $botNumber ? '0'.substr($botNumber, 2) : DB::raw('bot_whatsapp_number'),
                'bot_whatsapp_number_normalized' => $botNumber ?: DB::raw('bot_whatsapp_number_normalized'),
                'connection_status' => 'connected',
                'qr_status' => 'not_required',
                'webhook_status' => 'healthy',
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function extractBotNumber(array $rawPayload): ?string
    {
        $raw = (string) data_get($rawPayload, 'me.id', '');

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function maskSender(?string $sender): ?string
    {
        if ($sender === null || $sender === '') {
            return null;
        }

        $length = strlen($sender);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($sender, 0, 4).str_repeat('*', max(0, $length - 7)).substr($sender, -3);
    }

    private function hashSender(?string $sender): ?string
    {
        return $sender ? hash('sha256', $sender) : null;
    }
}
