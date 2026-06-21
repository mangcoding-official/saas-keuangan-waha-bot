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

        $this->conversationSessionService->expireStaleSessions($tenantUser, $messageTimestamp);
        $activeSession = $this->conversationSessionService->findActiveSession($tenantUser);

        if ($activeSession) {
            return $this->conversationSessionService->handleActiveSession(
                $activeSession,
                $tenantUser,
                $messageText,
                $messageTimestamp,
                $sourceMessageId,
                fn (TenantUser $user, string $text, Carbon $timestamp): array => $this->parser->parse($user, $text, $timestamp),
            );
        }

        if (in_array($normalized, ['menu', 'bantuan'], true)) {
            return [
                'route' => 'command_help',
                'should_reply' => true,
                'reply_text' => $this->helpText(),
                'side_effects' => ['command_help'],
            ];
        }

        if ($normalized === 'batal') {
            return [
                'route' => 'command_cancel_idle',
                'should_reply' => true,
                'reply_text' => 'Tidak ada proses aktif untuk dibatalkan.',
                'side_effects' => ['command_cancel_idle'],
            ];
        }

        $parsed = $this->parser->parse($tenantUser, $messageText, $messageTimestamp);

        if ($parsed['status'] === 'failed') {
            return [
                'route' => $parsed['route'],
                'should_reply' => true,
                'reply_text' => $parsed['reply_text'],
                'side_effects' => [$parsed['route']],
            ];
        }

        return $this->conversationSessionService->createSessionFromParserResult(
            $tenantUser,
            $parsed,
            $messageTimestamp,
            $sourceMessageId,
        );
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
