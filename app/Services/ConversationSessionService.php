<?php

namespace App\Services;

use App\Enums\ConversationIntentType;
use App\Enums\ConversationSessionStatus;
use App\Models\ConversationSession;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationSessionService
{
    public function __construct(
        private readonly TransactionRecordingService $transactionRecordingService,
    ) {
    }

    public function expireStaleSessions(TenantUser $tenantUser, Carbon $now): void
    {
        ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', ConversationSessionStatus::ACTIVE)
            ->where('expires_at', '<', $now)
            ->update([
                'status' => ConversationSessionStatus::EXPIRED,
                'active_lock' => null,
                'draft_payload' => null,
                'expired_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function findActiveSession(TenantUser $tenantUser): ?ConversationSession
    {
        return ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', ConversationSessionStatus::ACTIVE)
            ->whereNotNull('active_lock')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $parserResult
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    public function createSessionFromParserResult(
        TenantUser $tenantUser,
        array $parserResult,
        Carbon $messageTimestamp,
        ?string $sourceMessageId = null
    ): array {
        if ($parserResult['status'] === 'parsed' && $parserResult['payload'] !== null) {
            $session = $this->createReviewSession($tenantUser, $parserResult['payload'], $messageTimestamp, $sourceMessageId);

            return [
                'route' => 'review_confirm',
                'should_reply' => true,
                'reply_text' => $this->reviewPrompt($session),
                'side_effects' => ['session_created', 'review_confirm'],
            ];
        }

        if ($parserResult['status'] === 'needs_clarification') {
            $session = $this->createClarificationSession($tenantUser, $parserResult, $messageTimestamp, $sourceMessageId);

            return [
                'route' => $session->current_state,
                'should_reply' => true,
                'reply_text' => (string) $parserResult['reply_text'],
                'side_effects' => ['session_created', 'needs_clarification'],
            ];
        }

        return [
            'route' => (string) $parserResult['route'],
            'should_reply' => true,
            'reply_text' => (string) $parserResult['reply_text'],
            'side_effects' => [(string) $parserResult['route']],
        ];
    }

    /**
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    public function handleActiveSession(
        ConversationSession $session,
        TenantUser $tenantUser,
        string $messageText,
        Carbon $messageTimestamp,
        ?string $sourceMessageId,
        callable $parser
    ): array {
        $normalized = mb_strtolower(trim($messageText));

        if ($session->current_state === 'awaiting_interrupt_confirmation') {
            return $this->handleInterruptConfirmation($session, $normalized, $messageTimestamp);
        }

        if ($normalized === 'batal') {
            $this->cancelSession($session, $messageTimestamp);

            return [
                'route' => 'session_cancelled',
                'should_reply' => true,
                'reply_text' => 'Proses aktif dibatalkan.',
                'side_effects' => ['session_cancelled'],
            ];
        }

        if ($this->isInterruptCommand($normalized)) {
            $draft = $session->draft_payload ?? [];
            $draft['interrupt'] = [
                'previous_state' => $session->current_state,
                'pending_command' => $normalized,
            ];

            $session->forceFill([
                'current_state' => 'awaiting_interrupt_confirmation',
                'draft_payload' => $draft,
                'last_message_at' => $messageTimestamp,
                'expires_at' => $this->nextExpiry($messageTimestamp),
            ])->save();

            return [
                'route' => 'awaiting_interrupt_confirmation',
                'should_reply' => true,
                'reply_text' => "Masih ada proses yang belum selesai.\n\nBalas:\n1. lanjut\n2. batal",
                'side_effects' => ['session_interrupt_requested'],
            ];
        }

        if ($session->current_state === 'review_confirm') {
            if ($normalized === 'simpan') {
                return $this->commitReviewSession($session, $tenantUser, $messageTimestamp);
            }

            $session->forceFill([
                'last_message_at' => $messageTimestamp,
                'expires_at' => $this->nextExpiry($messageTimestamp),
            ])->save();

            return [
                'route' => 'review_confirm',
                'should_reply' => true,
                'reply_text' => $this->reviewPrompt($session),
                'side_effects' => ['review_repeated'],
            ];
        }

        if (str_starts_with($session->current_state, 'clarify_')) {
            $parsed = $parser($tenantUser, $messageText, $messageTimestamp);

            if ($parsed['status'] === 'parsed' && $parsed['payload'] !== null) {
                $this->updateSessionToReview($session, $parsed['payload'], $messageTimestamp, $sourceMessageId);

                return [
                    'route' => 'review_confirm',
                    'should_reply' => true,
                    'reply_text' => $this->reviewPrompt($session->fresh()),
                    'side_effects' => ['clarification_resolved', 'review_confirm'],
                ];
            }

            if ($parsed['status'] === 'needs_clarification') {
                $draft = $session->draft_payload ?? [];
                $draft['clarification'] = [
                    'state' => $parsed['clarification_state'] ?? $session->current_state,
                    'reply_text' => $parsed['reply_text'],
                    'original_message' => $messageText,
                ];

                $session->forceFill([
                    'current_state' => $parsed['clarification_state'] ?? $session->current_state,
                    'intent_type' => $this->normalizeIntentType($parsed['intent_type'] ?? null),
                    'draft_payload' => $draft,
                    'last_message_at' => $messageTimestamp,
                    'expires_at' => $this->nextExpiry($messageTimestamp),
                ])->save();

                return [
                    'route' => $session->current_state,
                    'should_reply' => true,
                    'reply_text' => (string) $parsed['reply_text'],
                    'side_effects' => ['clarification_repeated'],
                ];
            }

            $session->forceFill([
                'last_message_at' => $messageTimestamp,
                'expires_at' => $this->nextExpiry($messageTimestamp),
            ])->save();

            return [
                'route' => 'clarification_failed',
                'should_reply' => true,
                'reply_text' => (string) $parsed['reply_text'],
                'side_effects' => ['clarification_failed'],
            ];
        }

        return [
            'route' => 'session_state_unsupported',
            'should_reply' => true,
            'reply_text' => 'State session saat ini belum bisa diproses. Balas `batal` untuk menutup proses ini.',
            'side_effects' => ['session_state_unsupported'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createReviewSession(
        TenantUser $tenantUser,
        array $payload,
        Carbon $messageTimestamp,
        ?string $sourceMessageId
    ): ConversationSession {
        return ConversationSession::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'status' => ConversationSessionStatus::ACTIVE,
            'active_lock' => 1,
            'current_state' => 'review_confirm',
            'intent_type' => $this->normalizeIntentType($payload['type'] ?? null),
            'draft_payload' => $this->buildDraftPayload($payload, 'parser'),
            'source_message_id' => $sourceMessageId,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parserResult
     */
    private function createClarificationSession(
        TenantUser $tenantUser,
        array $parserResult,
        Carbon $messageTimestamp,
        ?string $sourceMessageId
    ): ConversationSession {
        return ConversationSession::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'status' => ConversationSessionStatus::ACTIVE,
            'active_lock' => 1,
            'current_state' => $parserResult['clarification_state'] ?? 'clarify_amount',
            'intent_type' => $this->normalizeIntentType($parserResult['intent_type'] ?? null),
            'draft_payload' => [
                'intent_type' => $parserResult['intent_type'] ?? null,
                'source' => 'parser',
                'items' => [],
                'transfer_admin_fee' => null,
                'attachment_ids' => [],
                'clarification' => [
                    'state' => $parserResult['clarification_state'] ?? 'clarify_amount',
                    'reply_text' => $parserResult['reply_text'],
                ],
                'review_ready' => false,
            ],
            'source_message_id' => $sourceMessageId,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateSessionToReview(
        ConversationSession $session,
        array $payload,
        Carbon $messageTimestamp,
        ?string $sourceMessageId
    ): void {
        $session->forceFill([
            'current_state' => 'review_confirm',
            'intent_type' => $this->normalizeIntentType($payload['type'] ?? null),
            'draft_payload' => $this->buildDraftPayload($payload, 'parser'),
            'source_message_id' => $sourceMessageId ?: $session->source_message_id,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
        ])->save();
    }

    private function cancelSession(ConversationSession $session, Carbon $now): void
    {
        $session->forceFill([
            'status' => ConversationSessionStatus::CANCELLED,
            'active_lock' => null,
            'draft_payload' => null,
            'cancelled_at' => $now,
            'updated_at' => $now,
        ])->save();
    }

    /**
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    private function handleInterruptConfirmation(
        ConversationSession $session,
        string $normalized,
        Carbon $messageTimestamp
    ): array {
        $normalized = match ($normalized) {
            '1', '1.', '1)' => 'lanjut',
            '2', '2.', '2)' => 'batal',
            default => $normalized,
        };

        $draft = $session->draft_payload ?? [];
        $previousState = data_get($draft, 'interrupt.previous_state', 'review_confirm');
        $pendingCommand = data_get($draft, 'interrupt.pending_command');

        if ($normalized === 'lanjut') {
            unset($draft['interrupt']);

            $session->forceFill([
                'current_state' => $previousState,
                'draft_payload' => $draft,
                'last_message_at' => $messageTimestamp,
                'expires_at' => $this->nextExpiry($messageTimestamp),
            ])->save();

            return [
                'route' => 'session_resumed',
                'should_reply' => true,
                'reply_text' => $this->promptForCurrentState($session->fresh()),
                'side_effects' => ['session_resumed'],
            ];
        }

        if ($normalized === 'batal') {
            $this->cancelSession($session, $messageTimestamp);

            return [
                'route' => 'session_cancelled',
                'should_reply' => true,
                'reply_text' => match ($pendingCommand) {
                    'menu', 'bantuan' => $this->helpText(),
                    'masuk' => 'Format cepat pemasukan: `masuk 15000 gaji` atau `masuk 1,5 juta bonus 15 juni`.',
                    'keluar' => 'Format cepat pengeluaran: `keluar 20rb makan` atau `keluar 75rb transport via cash default`.',
                    'transfer' => 'Format cepat transfer: `transfer 50rb dari cash default ke bca operasional`.',
                    default => 'Proses aktif dibatalkan.',
                },
                'side_effects' => ['session_cancelled'],
            ];
        }

        return [
            'route' => 'awaiting_interrupt_confirmation',
            'should_reply' => true,
            'reply_text' => "Masih ada proses yang belum selesai.\n\nBalas:\n1. lanjut\n2. batal",
            'side_effects' => ['session_interrupt_prompt_repeated'],
        ];
    }

    /**
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    private function commitReviewSession(ConversationSession $session, TenantUser $tenantUser, Carbon $now): array
    {
        $draft = $session->draft_payload ?? [];
        $items = data_get($draft, 'items', []);
        $savedCount = 0;

        DB::transaction(function () use ($items, $session, $tenantUser, &$savedCount, $now): void {
            foreach ($items as $item) {
                $this->transactionRecordingService->record(
                    $tenantUser,
                    $item,
                    $session->source_message_id,
                    $session->id,
                );
                $savedCount++;
            }

            $session->forceFill([
                'status' => ConversationSessionStatus::COMPLETED,
                'active_lock' => null,
                'draft_payload' => null,
                'completed_at' => $now,
                'updated_at' => $now,
            ])->save();
        });

        return [
            'route' => 'save_success',
            'should_reply' => true,
            'reply_text' => $savedCount === 1
                ? 'Transaksi sudah disimpan.'
                : 'Transaksi sudah disimpan sebanyak '.$savedCount.' item.',
            'side_effects' => ['session_completed', 'transaction_recorded'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildDraftPayload(array $payload, string $source): array
    {
        $item = [
            'type' => $payload['type'],
            'amount' => $payload['amount'],
            'description' => $payload['description'],
            'category_id' => $payload['category_id'] ?? null,
            'source_account_id' => $payload['source_account_id'] ?? ($payload['account_id'] ?? null),
            'destination_account_id' => $payload['destination_account_id'] ?? ($payload['account_id'] ?? null),
            'transaction_date' => $payload['transaction_date'] instanceof Carbon
                ? $payload['transaction_date']->toDateString()
                : (string) $payload['transaction_date'],
        ];

        if ($payload['type'] === ConversationIntentType::INCOME->value) {
            $item['source_account_id'] = null;
        }

        if ($payload['type'] === ConversationIntentType::EXPENSE->value) {
            $item['destination_account_id'] = null;
        }

        return [
            'intent_type' => $payload['type'],
            'source' => $source,
            'items' => [$item],
            'transfer_admin_fee' => null,
            'attachment_ids' => [],
            'clarification' => null,
            'review_ready' => true,
            'resolved_meta' => [
                'category_name' => $payload['category_name'] ?? null,
                'account_name' => $payload['account_name'] ?? null,
                'source_account_name' => $payload['source_account_name'] ?? null,
                'destination_account_name' => $payload['destination_account_name'] ?? null,
            ],
        ];
    }

    private function reviewPrompt(ConversationSession $session): string
    {
        $draft = $session->draft_payload ?? [];
        $item = data_get($draft, 'items.0', []);
        $meta = data_get($draft, 'resolved_meta', []);
        $date = Carbon::parse((string) ($item['transaction_date'] ?? now()->toDateString()))->format('d M Y');
        $amount = 'Rp '.number_format((float) ($item['amount'] ?? 0), 0, ',', '.');

        $lines = [
            'Review transaksi:',
            'Tipe: '.strtoupper((string) ($item['type'] ?? '-')),
            'Tanggal: '.$date,
            'Nominal: '.$amount,
            'Deskripsi: '.((string) ($item['description'] ?? '-') ?: '-'),
        ];

        if (($item['type'] ?? null) === ConversationIntentType::TRANSFER->value) {
            $lines[] = 'Dari: '.((string) ($meta['source_account_name'] ?? '-'));
            $lines[] = 'Ke: '.((string) ($meta['destination_account_name'] ?? '-'));
        } else {
            $lines[] = 'Kategori: '.((string) ($meta['category_name'] ?? '-'));
            $lines[] = 'Akun: '.((string) ($meta['account_name'] ?? '-'));
        }

        $lines[] = '';
        $lines[] = 'Balas `simpan` untuk menyimpan atau `batal` untuk membatalkan.';

        return implode("\n", $lines);
    }

    private function promptForCurrentState(ConversationSession $session): string
    {
        return match ($session->current_state) {
            'review_confirm' => $this->reviewPrompt($session),
            default => (string) data_get($session->draft_payload, 'clarification.reply_text', 'Lanjutkan proses yang sedang aktif atau balas `batal`.'),
        };
    }

    private function nextExpiry(Carbon $messageTimestamp): Carbon
    {
        return $messageTimestamp->copy()->addMinutes((int) config('platform.timeouts.conversation_session_minutes'));
    }

    private function normalizeIntentType(?string $type): ?ConversationIntentType
    {
        return $type ? ConversationIntentType::tryFrom($type) : null;
    }

    private function isInterruptCommand(string $normalized): bool
    {
        return in_array($normalized, ['menu', 'bantuan', 'masuk', 'keluar', 'transfer'], true);
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Perintah tersedia:',
            'masuk 15000 gaji',
            'keluar 20rb makan',
            'transfer 50rb dari cash default ke bca operasional',
            'ketik menu atau bantuan untuk lihat format ini lagi.',
        ]);
    }
}
