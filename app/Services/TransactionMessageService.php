<?php

namespace App\Services;

use App\Models\TenantUser;
use Illuminate\Support\Carbon;

class TransactionMessageService
{
    public function __construct(
        private readonly StructuredQuickTransactionParser $parser,
        private readonly ConversationSessionService $conversationSessionService,
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
            'masuk [nominal] [kategori] [tanggal]',
            'keluar [nominal] [kategori] [tanggal]',
            'transfer [nominal] dari [akun] ke [akun]',
            '',
            'Contoh:',
            'masuk 15rb gaji',
            'keluar 20rb makan 15 juni',
            'transfer 50rb dari cash default ke bca',
            'ketik menu atau bantuan untuk melihat perintah.',
        ]);
    }
}
