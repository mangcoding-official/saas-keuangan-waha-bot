<?php

namespace App\Services;

use App\Exceptions\AttachmentException;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionMessageService
{
    public function __construct(
        private readonly StructuredQuickTransactionParser $parser,
        private readonly ConversationSessionService $conversationSessionService,
        private readonly AttachmentStorageService $attachmentStorageService,
        private readonly BalanceInquiryService $balanceInquiryService,
    ) {
    }

    /**
     * @return array{
     *     route: string,
     *     should_reply: bool,
     *     reply_text: string,
     *     side_effects: array<int, string>
     * }
     */
    public function handle(
        TenantUser $tenantUser,
        string $messageText,
        Carbon $messageTimestamp,
        ?string $sourceMessageId = null
    ): array {
        $normalized = mb_strtolower(trim($messageText));

        $expiredSession = $this->conversationSessionService->expireStaleSessions($tenantUser, $messageTimestamp);
        $activeSession = $this->conversationSessionService->findActiveSession($tenantUser);

        if ($activeSession) {
            return $this->withExpiredNotice($this->conversationSessionService->handleActiveSession(
                $activeSession,
                $tenantUser,
                $messageText,
                $messageTimestamp,
                $sourceMessageId,
                fn (TenantUser $user, string $text, Carbon $timestamp): array => $this->parser->parse($user, $text, $timestamp),
            ), $expiredSession);
        }

        if (in_array($normalized, ['menu', 'bantuan'], true)) {
            return $this->withExpiredNotice([
                'route' => 'command_help',
                'should_reply' => true,
                'reply_text' => $this->helpText(),
                'side_effects' => ['command_help'],
            ], $expiredSession);
        }

        if ($normalized === 'saldo') {
            return $this->withExpiredNotice([
                'route' => 'command_balance',
                'should_reply' => true,
                'reply_text' => $this->balanceInquiryService->replyForTenantUser($tenantUser),
                'side_effects' => ['command_balance'],
            ], $expiredSession);
        }

        if ($normalized === 'batal') {
            return $this->withExpiredNotice([
                'route' => 'command_cancel_idle',
                'should_reply' => true,
                'reply_text' => 'Tidak ada proses aktif untuk dibatalkan.',
                'side_effects' => ['command_cancel_idle'],
            ], $expiredSession);
        }

        if (in_array($normalized, ['masuk', 'keluar', 'transfer'], true)) {
            return $this->withExpiredNotice($this->conversationSessionService->startGuidedSession(
                $tenantUser,
                $normalized,
                $messageTimestamp,
                $sourceMessageId,
            ), $expiredSession);
        }

        $parsed = $this->parser->parse($tenantUser, $messageText, $messageTimestamp);

        if ($parsed['status'] === 'failed') {
            return $this->withExpiredNotice([
                'route' => $parsed['route'],
                'should_reply' => true,
                'reply_text' => $parsed['reply_text'],
                'side_effects' => [$parsed['route']],
            ], $expiredSession);
        }

        return $this->withExpiredNotice($this->conversationSessionService->createSessionFromParserResult(
            $tenantUser,
            $parsed,
            $messageTimestamp,
            $sourceMessageId,
        ), $expiredSession);
    }

    /**
     * @param  array{url:string,mime_type:?string,file_name:?string,file_size:?int,width:?int,height:?int}  $media
     * @return array{route:string,should_reply:bool,reply_text:string,side_effects:array<int,string>}
     */
    public function handleAttachment(
        TenantUser $tenantUser,
        array $media,
        Carbon $messageTimestamp,
        string $sourceMessageId,
    ): array {
        $expiredSession = $this->conversationSessionService->expireStaleSessions($tenantUser, $messageTimestamp);
        $activeSession = $this->conversationSessionService->findActiveSession($tenantUser);

        if (! $activeSession || ! $this->conversationSessionService->acceptsAttachment($activeSession)) {
            return $this->withExpiredNotice([
                'route' => 'attachment_not_expected',
                'should_reply' => true,
                'reply_text' => 'Lampiran hanya dapat dikirim saat bot meminta bukti transaksi atau saat review transaksi income/expense masih aktif.',
                'side_effects' => ['attachment_rejected_no_active_flow'],
            ], $expiredSession);
        }

        try {
            $attachment = $this->attachmentStorageService->storeFromWaha(
                $tenantUser,
                $activeSession,
                $sourceMessageId,
                $media,
            );

            return $this->withExpiredNotice(
                $this->conversationSessionService->attachImage($activeSession, $tenantUser, $attachment, $messageTimestamp),
                $expiredSession,
            );
        } catch (AttachmentException $exception) {
            Log::warning('WAHA attachment failed', [
                'source_message_id' => $sourceMessageId,
                'tenant_user_id' => $tenantUser->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->withExpiredNotice([
                'route' => 'attachment_failed',
                'should_reply' => true,
                'reply_text' => $exception->getMessage(),
                'side_effects' => ['attachment_store_failed'],
            ], $expiredSession);
        } catch (Throwable $exception) {
            Log::error('WAHA attachment processing crashed', [
                'source_message_id' => $sourceMessageId,
                'tenant_user_id' => $tenantUser->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->withExpiredNotice([
                'route' => 'attachment_failed',
                'should_reply' => true,
                'reply_text' => 'Lampiran gagal diproses karena masalah internal. Silakan kirim ulang.',
                'side_effects' => ['attachment_processing_failed'],
            ], $expiredSession);
        }
    }

    /**
     * @param  array{route:string,should_reply:bool,reply_text:string,side_effects:array<int,string>}  $result
     * @return array{route:string,should_reply:bool,reply_text:string,side_effects:array<int,string>}
     */
    private function withExpiredNotice(array $result, bool $expiredSession): array
    {
        if (! $expiredSession || ! $result['should_reply']) {
            return $result;
        }

        $result['reply_text'] = trim("Proses sebelumnya sudah timeout karena tidak ada aktivitas selama 30 menit.\n\n".$result['reply_text']);
        $result['side_effects'][] = 'session_expired_notified';

        return $result;
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Perintah tersedia:',
            'masuk [nominal] [kategori]',
            'keluar [nominal] [kategori]',
            'saldo',
            'masuk [nominal] [kategori] [tanggal]',
            'keluar [nominal] [kategori] [tanggal]',
            'transfer [nominal] dari [akun] ke [akun]',
            '',
            'Contoh:',
            'masuk 15rb gaji',
            'keluar 20rb makan 15 juni',
            'transfer 50rb dari cash ke bca',
            'saldo',
            'ketik menu atau bantuan untuk melihat perintah.',
        ]);
    }
}
