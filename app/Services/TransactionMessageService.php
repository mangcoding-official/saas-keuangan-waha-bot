<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;

class TransactionMessageService
{
    public function __construct(
        private readonly StructuredQuickTransactionParser $parser,
        private readonly TransactionRecordingService $transactionRecordingService,
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
        $parsed = $this->parser->parse($tenantUser, $messageText, $messageTimestamp);

        if ($parsed['status'] !== 'parsed' || $parsed['payload'] === null) {
            return [
                'route' => $parsed['route'],
                'should_reply' => true,
                'reply_text' => $parsed['reply_text'],
                'side_effects' => [$parsed['route']],
            ];
        }

        $transaction = $this->transactionRecordingService->record($tenantUser, $parsed['payload'], $sourceMessageId);

        return [
            'route' => 'save_success',
            'should_reply' => true,
            'reply_text' => $this->successReply($parsed['payload']),
            'side_effects' => [
                'transaction_recorded',
                'transaction_type_'.$transaction->type->value,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function successReply(array $payload): string
    {
        $amount = 'Rp '.number_format((float) $payload['amount'], 0, ',', '.');
        $date = $payload['transaction_date']->format('d M Y');

        return match ($payload['type']) {
            TransactionType::INCOME->value => 'Pemasukan tersimpan: '.$amount.' ke '.$payload['account_name'].' untuk kategori '.$payload['category_name'].' pada '.$date.'.',
            TransactionType::EXPENSE->value => 'Pengeluaran tersimpan: '.$amount.' dari '.$payload['account_name'].' untuk kategori '.$payload['category_name'].' pada '.$date.'.',
            TransactionType::TRANSFER->value => 'Transfer tersimpan: '.$amount.' dari '.$payload['source_account_name'].' ke '.$payload['destination_account_name'].' pada '.$date.'.',
            default => 'Transaksi berhasil disimpan.',
        };
    }
}
