<?php

namespace App\Services\Waha;

use App\Enums\IncomingMessageAccessDecision;
use App\Enums\IncomingMessageIgnoredReason;
use App\Models\TenantUser;
use App\Services\TransactionMessageService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
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
        private readonly TransactionMessageService $transactionMessageService,
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
            return $this->ignoredResponse('ignored', ['invalid_payload']);
        }

        if ($message['event_name'] !== 'message') {
            return $this->ignoredResponse('ignored', ['unsupported_event_ignored']);
        }

        $lock = Cache::lock($this->messageLockKey($message['source_message_id']), 30);

        if (! $lock->get()) {
            return $this->duplicateResponse();
        }

        try {
            return $this->handleClaimedMessage($message);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{
     *     source_message_id: string,
     *     bot_instance_key: string,
     *     event_name: string,
     *     from_me: bool,
     *     chat_type: string,
     *     sender_raw: ?string,
     *     sender_normalized: ?string,
     *     chat_id: ?string,
     *     message_text: ?string,
     *     has_media: bool,
     *     media: ?array{url:string,mime_type:?string,file_name:?string,file_size:?int,width:?int,height:?int},
     *     message_timestamp: \Illuminate\Support\Carbon,
     *     raw_payload: array<string, mixed>
     * }  $message
     * @return array{
     *     status: string,
     *     route: string,
     *     should_reply: bool,
     *     reply_text: ?string,
     *     side_effects: array<int, string>
     * }
     */
    private function handleClaimedMessage(array $message): array
    {
        $existing = DB::table('incoming_messages')
            ->where('source_message_id', $message['source_message_id'])
            ->first(['id', 'processed_at']);

        if ($existing && $existing->processed_at !== null) {
            return $this->duplicateResponse();
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

                /** @var TenantUser|null $tenantUser */
                $tenantUser = $access['tenant_user']
                    ? TenantUser::query()->with('tenant')->find($access['tenant_user']->id)
                    : null;

                if ($tenantUser) {
                    if ($message['media'] !== null) {
                        $transactionResult = $this->transactionMessageService->handleAttachment(
                            $tenantUser,
                            $message['media'],
                            $message['message_timestamp'],
                            $message['source_message_id'],
                        );
                    } elseif ($message['has_media']) {
                        $transactionResult = [
                            'route' => 'attachment_media_unavailable',
                            'should_reply' => true,
                            'reply_text' => 'Gambar belum dapat diunduh dari WAHA. Silakan kirim ulang gambarnya.',
                            'side_effects' => ['attachment_media_unavailable_replied'],
                        ];
                    } elseif ($message['message_text'] !== null) {
                        $transactionResult = $this->transactionMessageService->handle(
                            $tenantUser,
                            $message['message_text'],
                            $message['message_timestamp'],
                            $message['source_message_id'],
                        );
                    } else {
                        $transactionResult = [
                            'route' => 'unsupported_message_type',
                            'should_reply' => true,
                            'reply_text' => 'Jenis pesan belum didukung. Kirim teks perintah atau gambar bukti transaksi.',
                            'side_effects' => ['unsupported_message_type_replied'],
                        ];
                    }

                    $route = $transactionResult['route'];
                    $shouldReply = $transactionResult['should_reply'];
                    $replyText = $transactionResult['reply_text'];
                    $sideEffects = array_merge($sideEffects, $transactionResult['side_effects']);
                } else {
                    $route = 'unsupported_command';
                    $shouldReply = true;
                    $replyText = 'Perintah tidak dikenali.';
                    $sideEffects[] = 'unsupported_command_replied';
                }
            }

            if ($ignoredReason === IncomingMessageIgnoredReason::PENDING_VERIFICATION->value && $access['tenant_user']) {
                $shouldReply = true;
                $replyText = 'Perintah tidak dikenali. Nomor kamu masih pending verification. Gunakan: AKTIF KAS-XXXX.';
                $sideEffects[] = 'pending_verification_guidance_sent';
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
            $route === 'ignored' && $access['tenant_user'] === null ? null : $message['message_text'],
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

    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     should_reply: bool,
     *     reply_text: null,
     *     side_effects: array<int, string>
     * }
     */
    private function duplicateResponse(): array
    {
        return $this->ignoredResponse('duplicate_ignored', ['duplicate_ignored']);
    }

    /**
     * @param  array<int, string>  $sideEffects
     * @return array{
     *     status: string,
     *     route: string,
     *     should_reply: bool,
     *     reply_text: null,
     *     side_effects: array<int, string>
     * }
     */
    private function ignoredResponse(string $route, array $sideEffects): array
    {
        return [
            'status' => $route === 'ignored' ? 'ignored' : 'ok',
            'route' => $route,
            'should_reply' => false,
            'reply_text' => null,
            'side_effects' => $sideEffects,
        ];
    }

    private function messageLockKey(string $sourceMessageId): string
    {
        return 'waha:webhook:message:'.$sourceMessageId;
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
