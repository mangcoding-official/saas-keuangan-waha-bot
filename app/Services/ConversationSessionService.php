<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Enums\ConversationIntentType;
use App\Enums\ConversationSessionStatus;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\ConversationSession;
use App\Models\TenantUser;
use App\Support\CategoryCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationSessionService
{
    public function __construct(
        private readonly TransactionRecordingService $transactionRecordingService,
    ) {
    }

    public function expireStaleSessions(TenantUser $tenantUser, Carbon $now): bool
    {
        $expiredCount = ConversationSession::query()
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

        return $expiredCount > 0;
    }

    public function expireAllStaleSessions(Carbon $now): int
    {
        return ConversationSession::query()
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
        $session = ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', ConversationSessionStatus::ACTIVE)
            ->whereNotNull('active_lock')
            ->latest('id')
            ->first();

        if ($session !== null) {
            return $session;
        }

        $recoverableSession = ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', ConversationSessionStatus::ACTIVE)
            ->latest('id')
            ->first();

        if ($recoverableSession === null) {
            return null;
        }

        if ($recoverableSession->active_lock === null) {
            $recoverableSession->forceFill([
                'active_lock' => 1,
                'updated_at' => now(),
            ])->save();
        }

        return $recoverableSession->fresh();
    }

    public function recoverRecentSession(TenantUser $tenantUser, Carbon $now): ?ConversationSession
    {
        $recoverableSession = ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->whereIn('status', [
                ConversationSessionStatus::ACTIVE,
                ConversationSessionStatus::EXPIRED,
            ])
            ->latest('last_message_at')
            ->latest('id')
            ->first();

        if ($recoverableSession === null || ! $this->canRecoverRecentSession($recoverableSession, $now)) {
            return null;
        }

        $recoverableSession->forceFill([
            'status' => ConversationSessionStatus::ACTIVE,
            'active_lock' => 1,
            'expired_at' => null,
            'updated_at' => $now,
        ])->save();

        return $recoverableSession->fresh();
    }

    /**
     * @return array{
     *     id:int,
     *     status:string,
     *     current_state:?string,
     *     active_lock:mixed,
     *     source_message_id:?string,
     *     last_message_at:?string,
     *     expires_at:?string,
     *     expired_at:?string
     * }|null
     */
    public function latestSessionSummary(TenantUser $tenantUser): ?array
    {
        $session = ConversationSession::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->latest('last_message_at')
            ->latest('id')
            ->first([
                'id',
                'status',
                'current_state',
                'active_lock',
                'source_message_id',
                'last_message_at',
                'expires_at',
                'expired_at',
            ]);

        if ($session === null) {
            return null;
        }

        return [
            'id' => (int) $session->id,
            'status' => (string) $session->status,
            'current_state' => $session->current_state,
            'active_lock' => $session->active_lock,
            'source_message_id' => $session->source_message_id,
            'last_message_at' => $session->last_message_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'expired_at' => $session->expired_at?->toIso8601String(),
        ];
    }

    public function acceptsAttachment(ConversationSession $session): bool
    {
        return in_array($session->intent_type, [
            ConversationIntentType::INCOME,
            ConversationIntentType::EXPENSE,
        ], true) && in_array($session->current_state, [
            'guided_income_attachment_offer',
            'guided_expense_attachment_offer',
            'review_confirm',
        ], true);
    }

    /**
     * @return array{route:string,should_reply:bool,reply_text:string,side_effects:array<int,string>}
     */
    public function attachImage(
        ConversationSession $session,
        TenantUser $tenantUser,
        Attachment $attachment,
        Carbon $messageTimestamp,
    ): array {
        if (
            ! $this->acceptsAttachment($session)
            || $session->tenant_id !== $tenantUser->tenant_id
            || $session->tenant_user_id !== $tenantUser->id
            || $attachment->tenant_id !== $tenantUser->tenant_id
            || $attachment->conversation_session_id !== $session->id
        ) {
            throw new \RuntimeException('Lampiran tidak sesuai dengan flow transaksi yang aktif.');
        }

        $draft = $session->draft_payload ?? [];
        $attachmentIds = array_values(array_unique(array_map(
            'intval',
            array_merge((array) data_get($draft, 'attachment_ids', []), [$attachment->id]),
        )));

        data_set($draft, 'attachment_ids', $attachmentIds);
        data_set($draft, 'review_ready', true);

        $session->forceFill([
            'current_state' => 'review_confirm',
            'draft_payload' => $draft,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
        ])->save();

        return [
            'route' => 'attachment_added',
            'should_reply' => true,
            'reply_text' => "Lampiran berhasil ditambahkan.\n\n".$this->reviewPrompt($session->fresh()),
            'side_effects' => ['attachment_stored', 'attachment_added_to_session', 'review_confirm'],
        ];
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
    public function startGuidedSession(
        TenantUser $tenantUser,
        string $command,
        Carbon $messageTimestamp,
        ?string $sourceMessageId = null
    ): array {
        $intent = match ($command) {
            'masuk' => ConversationIntentType::INCOME,
            'keluar' => ConversationIntentType::EXPENSE,
            'transfer' => ConversationIntentType::TRANSFER,
            default => ConversationIntentType::OTHER,
        };

        $session = ConversationSession::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'status' => ConversationSessionStatus::ACTIVE,
            'active_lock' => 1,
            'current_state' => $this->initialGuidedState($intent),
            'intent_type' => $intent,
            'draft_payload' => $this->buildInitialGuidedDraft($intent),
            'source_message_id' => $sourceMessageId,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
        ]);

        return [
            'route' => $session->current_state,
            'should_reply' => true,
            'reply_text' => $this->promptForCurrentState($session),
            'side_effects' => ['session_created', 'guided_started'],
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
            return $this->handleInterruptConfirmation(
                $session,
                $tenantUser,
                $normalized,
                $messageTimestamp,
                $sourceMessageId,
                $parser,
            );
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

        if ($this->isInterruptCommand($normalized, $session->current_state)) {
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

        if (str_starts_with($session->current_state, 'guided_')) {
            return $this->handleGuidedState($session, $tenantUser, $messageText, $messageTimestamp, $sourceMessageId);
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
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    private function handleGuidedState(
        ConversationSession $session,
        TenantUser $tenantUser,
        string $messageText,
        Carbon $messageTimestamp,
        ?string $sourceMessageId
    ): array {
        $draft = $session->draft_payload ?? $this->buildInitialGuidedDraft($session->intent_type ?? ConversationIntentType::OTHER);
        $item = data_get($draft, 'items.0', []);
        $normalized = mb_strtolower(trim($messageText));

        switch ($session->current_state) {
            case 'guided_income_amount':
            case 'guided_expense_amount':
            case 'guided_transfer_amount':
                $amount = $this->extractAmountValue($messageText);

                if ($amount === null || $amount <= 0) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Nominal belum valid. Kirim angka seperti `15000`, `15rb`, atau `1,5 juta`.');
                }

                data_set($draft, 'items.0.amount', $amount);
                $nextState = match ($session->current_state) {
                    'guided_income_amount' => 'guided_income_description',
                    'guided_expense_amount' => 'guided_expense_description',
                    default => 'guided_transfer_source_account',
                };

                return $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp);

            case 'guided_income_description':
            case 'guided_expense_description':
                if ($normalized === '') {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Keterangan wajib diisi.');
                }

                data_set($draft, 'items.0.description', trim($messageText));
                $nextState = $session->current_state === 'guided_income_description'
                    ? 'guided_income_category'
                    : 'guided_expense_category';

                return $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp);

            case 'guided_income_category':
            case 'guided_expense_category':
                $categoryType = $session->current_state === 'guided_income_category'
                    ? CategoryType::INCOME
                    : CategoryType::EXPENSE;
                $category = $this->resolveCategory($tenantUser, $messageText, $categoryType);
                $category ??= $this->resolveFallbackCategory($tenantUser, $categoryType);

                if ($category === null) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Kategori fallback untuk tenant ini belum tersedia. Owner perlu menyiapkan kategori aktif.');
                }

                data_set($draft, 'items.0.category_id', $category->id);
                data_set($draft, 'resolved_meta.category_name', $category->name);
                $nextState = $session->current_state === 'guided_income_category'
                    ? 'guided_income_destination_account'
                    : 'guided_expense_source_account';

                return $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp);

            case 'guided_income_destination_account':
            case 'guided_expense_source_account':
                $account = $this->resolveAccountForGuided($tenantUser, $messageText, true);

                if ($account === null) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Akun belum dikenali. Kirim nama akun aktif atau balas `default`.');
                }

                if ($session->current_state === 'guided_income_destination_account') {
                    data_set($draft, 'items.0.destination_account_id', $account->id);
                } else {
                    data_set($draft, 'items.0.source_account_id', $account->id);
                }

                data_set($draft, 'resolved_meta.account_name', $account->name);
                $nextState = $session->current_state === 'guided_income_destination_account'
                    ? 'guided_income_date'
                    : 'guided_expense_date';

                return $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp);

            case 'guided_income_date':
            case 'guided_expense_date':
            case 'guided_transfer_date':
                $date = $this->resolveGuidedDate($messageText, $messageTimestamp, $tenantUser);

                if ($date === null) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Tanggal belum valid. Contoh: `hari ini`, `kemarin`, `15 juni`, atau `2026-06-15`.');
                }

                data_set($draft, 'items.0.transaction_date', $date->toDateString());

                $nextState = match ($session->current_state) {
                    'guided_income_date' => 'guided_income_attachment_offer',
                    'guided_expense_date' => 'guided_expense_attachment_offer',
                    default => 'guided_transfer_description',
                };

                return $nextState === 'guided_transfer_description'
                    ? $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp)
                    : $this->advanceGuidedSession($session, $draft, $nextState, $messageTimestamp);

            case 'guided_income_attachment_offer':
            case 'guided_expense_attachment_offer':
                if (! in_array($normalized, ['skip', 'tidak', 'ga', 'gak', 'lanjut', 'tidak ada'], true)) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Kirim gambar bukti atau balas `skip` / `lanjut` jika tidak ada lampiran.');
                }

                data_set($draft, 'review_ready', true);

                return $this->moveGuidedSessionToReview($session, $draft, $messageTimestamp, $sourceMessageId);

            case 'guided_transfer_source_account':
                $sourceAccount = $this->resolveAccountForGuided($tenantUser, $messageText, false);

                if ($sourceAccount === null) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Akun sumber belum dikenali. Kirim nama akun aktif.');
                }

                data_set($draft, 'items.0.source_account_id', $sourceAccount->id);
                data_set($draft, 'resolved_meta.source_account_name', $sourceAccount->name);

                return $this->advanceGuidedSession($session, $draft, 'guided_transfer_destination_account', $messageTimestamp);

            case 'guided_transfer_destination_account':
                $destinationAccount = $this->resolveAccountForGuided($tenantUser, $messageText, false);
                $sourceAccountId = (int) data_get($draft, 'items.0.source_account_id');

                if ($destinationAccount === null) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Akun tujuan belum dikenali. Kirim nama akun aktif.');
                }

                if ($destinationAccount->id === $sourceAccountId) {
                    return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Akun sumber dan tujuan tidak boleh sama.');
                }

                data_set($draft, 'items.0.destination_account_id', $destinationAccount->id);
                data_set($draft, 'resolved_meta.destination_account_name', $destinationAccount->name);

                return $this->advanceGuidedSession($session, $draft, 'guided_transfer_admin_fee', $messageTimestamp);

            case 'guided_transfer_admin_fee':
                if (in_array($normalized, ['skip', 'tidak', 'ga', 'gak', '0', 'tidak ada'], true)) {
                    data_set($draft, 'transfer_admin_fee', null);
                } else {
                    $fee = $this->extractAmountValue($messageText);

                    if ($fee === null || $fee < 0) {
                        return $this->repeatGuidedPrompt($session, $messageTimestamp, 'Biaya admin belum valid. Kirim angka nominal atau balas `0` / `skip`.');
                    }

                    data_set($draft, 'transfer_admin_fee', $fee > 0 ? $fee : null);
                }

                return $this->advanceGuidedSession($session, $draft, 'guided_transfer_date', $messageTimestamp);

            case 'guided_transfer_description':
                data_set(
                    $draft,
                    'items.0.description',
                    in_array($normalized, ['skip', 'tidak', 'ga', 'gak', 'tidak ada', 'kosong'], true) ? null : trim($messageText)
                );
                data_set($draft, 'review_ready', true);

                return $this->moveGuidedSessionToReview($session, $draft, $messageTimestamp, $sourceMessageId);
        }

        return $this->repeatGuidedPrompt($session, $messageTimestamp, null);
    }

    private function initialGuidedState(ConversationIntentType $intent): string
    {
        return match ($intent) {
            ConversationIntentType::INCOME => 'guided_income_amount',
            ConversationIntentType::EXPENSE => 'guided_expense_amount',
            ConversationIntentType::TRANSFER => 'guided_transfer_amount',
            default => 'guided_expense_amount',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialGuidedDraft(ConversationIntentType $intent): array
    {
        return [
            'intent_type' => $intent->value,
            'source' => 'guided',
            'items' => [[
                'type' => $intent->value,
                'amount' => null,
                'description' => null,
                'category_id' => null,
                'source_account_id' => null,
                'destination_account_id' => null,
                'transaction_date' => null,
            ]],
            'transfer_admin_fee' => null,
            'attachment_ids' => [],
            'clarification' => null,
            'review_ready' => false,
            'resolved_meta' => [
                'category_name' => null,
                'account_name' => null,
                'source_account_name' => null,
                'destination_account_name' => null,
            ],
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
    private function advanceGuidedSession(
        ConversationSession $session,
        array $draft,
        string $nextState,
        Carbon $messageTimestamp
    ): array {
        $session->forceFill([
            'current_state' => $nextState,
            'draft_payload' => $draft,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
        ])->save();

        return [
            'route' => $nextState,
            'should_reply' => true,
            'reply_text' => $this->promptForCurrentState($session->fresh()),
            'side_effects' => ['guided_state_advanced'],
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
    private function moveGuidedSessionToReview(
        ConversationSession $session,
        array $draft,
        Carbon $messageTimestamp,
        ?string $sourceMessageId
    ): array {
        $session->forceFill([
            'current_state' => 'review_confirm',
            'draft_payload' => $draft,
            'source_message_id' => $sourceMessageId ?: $session->source_message_id,
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
        ])->save();

        return [
            'route' => 'review_confirm',
            'should_reply' => true,
            'reply_text' => $this->reviewPrompt($session->fresh()),
            'side_effects' => ['guided_review_ready'],
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
    private function repeatGuidedPrompt(
        ConversationSession $session,
        Carbon $messageTimestamp,
        ?string $prefix
    ): array {
        $session->forceFill([
            'last_message_at' => $messageTimestamp,
            'expires_at' => $this->nextExpiry($messageTimestamp),
        ])->save();

        $prompt = $this->promptForCurrentState($session);
        $reply = $prefix ? trim($prefix."\n\n".$prompt) : $prompt;

        return [
            'route' => $session->current_state,
            'should_reply' => true,
            'reply_text' => $reply,
            'side_effects' => ['guided_validation_failed'],
        ];
    }

    private function extractAmountValue(string $text): ?float
    {
        $normalized = trim(preg_replace('/\s+/u', '', mb_strtolower($text)) ?? '');

        if ($normalized === '') {
            return null;
        }

        $multiplier = 1;

        if (str_contains($normalized, 'juta') || str_contains($normalized, 'jt')) {
            $multiplier = 1000000;
            $normalized = str_replace(['juta', 'jt'], '', $normalized);
        } elseif (str_contains($normalized, 'ribu') || str_contains($normalized, 'rb') || preg_match('/k$/', $normalized) === 1) {
            $multiplier = 1000;
            $normalized = str_replace(['ribu', 'rb', 'k'], '', $normalized);
        }

        if ($normalized === '') {
            return null;
        }

        $normalized = $multiplier > 1
            ? str_replace(',', '.', $normalized)
            : str_replace(',', '', str_replace('.', '', $normalized));

        if (preg_match('/^\d+(?:\.\d+)?$/', $normalized) !== 1) {
            return null;
        }

        return (float) $normalized * $multiplier;
    }

    private function resolveCategory(TenantUser $tenantUser, string $text, CategoryType $type): ?Category
    {
        $normalizedText = $this->normalizeForMatch($text);
        $bestMatch = null;
        $bestLength = 0;

        $categories = Category::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            foreach ([$category->name, ...($category->keywords ?? [])] as $candidate) {
                $needle = $this->normalizeForMatch((string) $candidate);

                if ($needle !== '' && str_contains($normalizedText, $needle) && strlen($needle) > $bestLength) {
                    $bestMatch = $category;
                    $bestLength = strlen($needle);
                }
            }
        }

        return $bestMatch;
    }

    private function resolveFallbackCategory(TenantUser $tenantUser, CategoryType $type): ?Category
    {
        $fallbackKey = CategoryCatalog::fallbackKeyFor($tenantUser->tenant->tenant_type, $type);

        return Category::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('type', $type)
            ->where('key', $fallbackKey)
            ->where('is_active', true)
            ->first();
    }

    private function resolveAccountForGuided(TenantUser $tenantUser, string $text, bool $allowDefaultKeyword): ?Account
    {
        $normalized = $this->normalizeForMatch($text);

        if ($allowDefaultKeyword && in_array($normalized, ['default', 'akun default', 'pakai default', 'gunakan default', 'skip', 'lanjut'], true)) {
            return Account::query()
                ->where('tenant_id', $tenantUser->tenant_id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
        }

        $accounts = Account::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $bestMatch = null;
        $bestLength = 0;

        foreach ($accounts as $account) {
            $needle = $this->normalizeForMatch($account->name);

            if ($needle !== '' && str_contains($normalized, $needle) && strlen($needle) > $bestLength) {
                $bestMatch = $account;
                $bestLength = strlen($needle);
            }
        }

        return $bestMatch;
    }

    private function resolveGuidedDate(string $text, Carbon $messageTimestamp, TenantUser $tenantUser): ?Carbon
    {
        $normalized = mb_strtolower(trim($text));
        $now = $messageTimestamp->copy()->timezone($tenantUser->tenant->timezone);

        if (in_array($normalized, ['skip', 'default', 'lanjut', 'hari ini', 'sekarang'], true)) {
            return $now->copy()->startOfDay();
        }

        if ($normalized === 'kemarin') {
            return $now->copy()->subDay()->startOfDay();
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/u', $normalized, $matches) === 1) {
            return Carbon::createFromDate((int) $matches[1], (int) $matches[2], (int) $matches[3], $now->timezone);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/u', $normalized, $matches) === 1) {
            return Carbon::createFromDate((int) $matches[3], (int) $matches[2], (int) $matches[1], $now->timezone);
        }

        if (preg_match('/^tanggal\s+(\d{1,2})$/u', $normalized, $matches) === 1) {
            return Carbon::createFromDate((int) $now->format('Y'), (int) $now->format('m'), (int) $matches[1], $now->timezone);
        }

        if (preg_match('/^(\d{1,2})\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+(\d{4}))?$/u', $normalized, $matches) === 1) {
            return Carbon::createFromDate(
                isset($matches[3]) ? (int) $matches[3] : (int) $now->format('Y'),
                $this->indonesianMonthNumber($matches[2]),
                (int) $matches[1],
                $now->timezone,
            );
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTransactionsForCommit(ConversationSession $session, TenantUser $tenantUser): array
    {
        $draft = $session->draft_payload ?? [];
        $items = data_get($draft, 'items', []);

        if (($session->intent_type?->value ?? null) !== ConversationIntentType::TRANSFER->value) {
            return $items;
        }

        $fee = data_get($draft, 'transfer_admin_fee');

        if (! is_numeric($fee) || (float) $fee <= 0) {
            return $items;
        }

        $adminCategory = $this->resolveFallbackCategory($tenantUser, CategoryType::EXPENSE);

        if (! $adminCategory) {
            return $items;
        }

        $firstItem = $items[0] ?? null;

        if (! is_array($firstItem)) {
            return $items;
        }

        $items[] = [
            'type' => ConversationIntentType::EXPENSE->value,
            'amount' => (float) $fee,
            'description' => 'Biaya Admin',
            'category_id' => $adminCategory->id,
            'source_account_id' => $firstItem['source_account_id'] ?? null,
            'destination_account_id' => null,
            'transaction_date' => $firstItem['transaction_date'] ?? now()->toDateString(),
        ];

        return $items;
    }

    private function indonesianMonthNumber(string $month): int
    {
        return match (mb_strtolower($month)) {
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
            default => 1,
        };
    }

    private function normalizeForMatch(string $value): string
    {
        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
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
        TenantUser $tenantUser,
        string $normalized,
        Carbon $messageTimestamp,
        ?string $sourceMessageId,
        callable $parser,
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

            if (in_array($pendingCommand, ['masuk', 'keluar', 'transfer'], true)) {
                return $this->startGuidedSession($tenantUser, (string) $pendingCommand, $messageTimestamp, $sourceMessageId);
            }

            if ($pendingCommand === 'saldo') {
                return [
                    'route' => 'command_balance',
                    'should_reply' => true,
                    'reply_text' => app(BalanceInquiryService::class)->replyForTenantUser($tenantUser),
                    'side_effects' => ['command_balance'],
                ];
            }

            if (in_array($pendingCommand, ['transaksi terakhir', 'latest transaction'], true)) {
                return [
                    'route' => 'command_latest_transactions',
                    'should_reply' => true,
                    'reply_text' => app(BalanceInquiryService::class)->latestTransactionsReplyForTenantUser($tenantUser),
                    'side_effects' => ['command_latest_transactions'],
                ];
            }

            if (is_string($pendingCommand) && preg_match('/^(masuk|keluar|transfer)\b/u', $pendingCommand) === 1) {
                $parsed = $parser($tenantUser, $pendingCommand, $messageTimestamp);

                return $this->createSessionFromParserResult(
                    $tenantUser,
                    $parsed,
                    $messageTimestamp,
                    $sourceMessageId,
                );
            }

            return [
                'route' => 'session_cancelled',
                'should_reply' => true,
                'reply_text' => match ($pendingCommand) {
                    'menu', 'bantuan' => $this->helpText(),
                    'saldo' => app(BalanceInquiryService::class)->replyForTenantUser($tenantUser),
                    'transaksi terakhir', 'latest transaction' => app(BalanceInquiryService::class)->latestTransactionsReplyForTenantUser($tenantUser),
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
        $items = $this->buildTransactionsForCommit($session, $tenantUser);
        $attachmentIds = Attachment::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('uploaded_by_user_id', $tenantUser->id)
            ->where('conversation_session_id', $session->id)
            ->whereIn('id', (array) data_get($session->draft_payload, 'attachment_ids', []))
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
        $savedCount = 0;

        DB::transaction(function () use ($items, $attachmentIds, $session, $tenantUser, &$savedCount, $now): void {
            foreach ($items as $item) {
                $transaction = $this->transactionRecordingService->record(
                    $tenantUser,
                    $item,
                    $session->source_message_id,
                    $session->id,
                );

                foreach ($attachmentIds as $attachmentId) {
                    DB::table('attachment_transaction')->insertOrIgnore([
                        'attachment_id' => $attachmentId,
                        'transaction_id' => $transaction->id,
                        'created_at' => $now,
                    ]);
                }

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

        $sideEffects = ['session_completed', 'transaction_recorded'];

        if ($attachmentIds !== []) {
            $sideEffects[] = 'attachment_linked_to_transaction';
        }

        return [
            'route' => 'save_success',
            'should_reply' => true,
            'reply_text' => $savedCount === 1
                ? 'Transaksi sudah disimpan'.($attachmentIds !== [] ? ' beserta lampirannya.' : '.')
                : 'Transaksi sudah disimpan sebanyak '.$savedCount.' item'.($attachmentIds !== [] ? ' beserta lampirannya.' : '.'),
            'side_effects' => $sideEffects,
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
        ];

        if (filled($item['description'] ?? null)) {
            $lines[] = 'Deskripsi: '.(string) $item['description'];
        }

        if (($item['type'] ?? null) === ConversationIntentType::TRANSFER->value) {
            $lines[] = 'Dari: '.((string) ($meta['source_account_name'] ?? '-'));
            $lines[] = 'Ke: '.((string) ($meta['destination_account_name'] ?? '-'));
        } else {
            $lines[] = 'Kategori: '.((string) ($meta['category_name'] ?? '-'));
            $lines[] = 'Akun: '.((string) ($meta['account_name'] ?? '-'));
        }

        $lines[] = '';

        if (($item['type'] ?? null) !== ConversationIntentType::TRANSFER->value) {
            $attachmentCount = count((array) data_get($draft, 'attachment_ids', []));
            $lines[] = 'Lampiran: '.$attachmentCount.' gambar';
            $lines[] = 'Kirim gambar bukti jika ada.';
        }

        $lines[] = 'Balas `simpan` untuk menyimpan atau `batal` untuk membatalkan.';

        return implode("\n", $lines);
    }

    private function promptForCurrentState(ConversationSession $session): string
    {
        return match ($session->current_state) {
            'guided_income_amount' => 'Masukkan nominal pemasukan. Contoh: `15000`, `15rb`, atau `1,5 juta`.',
            'guided_income_description' => 'Masukkan keterangan pemasukan. Contoh: `bonus goal project`.',
            'guided_income_category' => 'Masukkan kategori pemasukan. Opsi aktif: '.$this->listCategoryNames($session->tenant_id, CategoryType::INCOME).'.',
            'guided_income_destination_account' => 'Masukkan akun tujuan. Opsi aktif: '.$this->listAccountNames($session->tenant_id).'. Balas nama akun atau `default`.',
            'guided_income_date' => 'Masukkan tanggal pemasukan. Contoh: `hari ini`, `kemarin`, `15 juni`, atau `2026-06-15`.',
            'guided_income_attachment_offer' => 'Kirim gambar bukti pemasukan. Jika tidak ada, balas `skip` atau `lanjut`.',
            'guided_expense_amount' => 'Masukkan nominal pengeluaran. Contoh: `15000`, `15rb`, atau `1,5 juta`.',
            'guided_expense_description' => 'Masukkan keterangan pengeluaran. Contoh: `makan siang tim`.',
            'guided_expense_category' => 'Masukkan kategori pengeluaran. Opsi aktif: '.$this->listCategoryNames($session->tenant_id, CategoryType::EXPENSE).'.',
            'guided_expense_source_account' => 'Masukkan akun sumber. Opsi aktif: '.$this->listAccountNames($session->tenant_id).'. Balas nama akun atau `default`.',
            'guided_expense_date' => 'Masukkan tanggal pengeluaran. Contoh: `hari ini`, `kemarin`, `15 juni`, atau `2026-06-15`.',
            'guided_expense_attachment_offer' => 'Kirim gambar bukti pengeluaran. Jika tidak ada, balas `skip` atau `lanjut`.',
            'guided_transfer_amount' => 'Masukkan nominal transfer. Contoh: `50000` atau `50rb`.',
            'guided_transfer_source_account' => 'Masukkan akun sumber transfer. Opsi aktif: '.$this->listAccountNames($session->tenant_id).'.',
            'guided_transfer_destination_account' => 'Masukkan akun tujuan transfer. Opsi aktif: '.$this->listAccountNames($session->tenant_id).'.',
            'guided_transfer_admin_fee' => 'Masukkan biaya admin jika ada. Balas `0` atau `skip` jika tidak ada.',
            'guided_transfer_date' => 'Masukkan tanggal transfer. Contoh: `hari ini`, `kemarin`, `15 juni`, atau `2026-06-15`.',
            'guided_transfer_description' => 'Masukkan keterangan transfer jika perlu. Balas `skip` jika tidak ada.',
            'review_confirm' => $this->reviewPrompt($session),
            default => (string) data_get($session->draft_payload, 'clarification.reply_text', 'Lanjutkan proses yang sedang aktif atau balas `batal`.'),
        };
    }

    private function listAccountNames(int $tenantId): string
    {
        $names = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names === [] ? '-' : implode(', ', $names);
    }

    private function listCategoryNames(int $tenantId, CategoryType $type): string
    {
        $names = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names === [] ? '-' : implode(', ', $names);
    }

    private function nextExpiry(Carbon $messageTimestamp): Carbon
    {
        return $messageTimestamp->copy()->addMinutes((int) config('platform.timeouts.conversation_session_minutes'));
    }

    private function canRecoverRecentSession(ConversationSession $session, Carbon $now): bool
    {
        if (! in_array($session->status, [ConversationSessionStatus::ACTIVE, ConversationSessionStatus::EXPIRED], true)) {
            return false;
        }

        if (! $this->isRecoverableState($session->current_state)) {
            return false;
        }

        return $session->last_message_at !== null
            && $session->last_message_at->gte($now->copy()->subMinutes(5));
    }

    private function isRecoverableState(string $state): bool
    {
        return str_starts_with($state, 'guided_')
            || str_starts_with($state, 'clarify_')
            || in_array($state, ['review_confirm', 'awaiting_interrupt_confirmation'], true);
    }

    private function normalizeIntentType(?string $type): ?ConversationIntentType
    {
        return $type ? ConversationIntentType::tryFrom($type) : null;
    }

    private function isInterruptCommand(string $normalized, string $currentState): bool
    {
        if (in_array($normalized, ['menu', 'bantuan'], true)) {
            return true;
        }

        if (str_starts_with($currentState, 'clarify_')) {
            return false;
        }

        return preg_match('/^(masuk|keluar|transfer|saldo)\b/u', $normalized) === 1
            || in_array($normalized, ['transaksi terakhir', 'latest transaction'], true);
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Perintah tersedia:',
            'masuk [nominal] [kategori]',
            'keluar [nominal] [kategori]',
            'saldo',
            'transaksi terakhir',
            'masuk [nominal] [kategori] [tanggal]',
            'keluar [nominal] [kategori] [tanggal]',
            'transfer [nominal] dari [akun] ke [akun]',
            '',
            'Contoh:',
            'masuk 15rb gaji',
            'keluar 20rb makan 15 juni',
            'transfer 50rb dari cash ke bca',
            'saldo',
            'transaksi terakhir',
            'ketik menu atau bantuan untuk melihat perintah.',
        ]);
    }
}
