<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\TenantUser;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class TransactionRecordingService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(TenantUser $tenantUser, array $payload, ?string $sourceMessageId = null): Transaction
    {
        $type = $payload['type'];
        $transactionDate = $payload['transaction_date'];

        return Transaction::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'recorded_by_user_id' => $tenantUser->id,
            'conversation_session_id' => null,
            'type' => $type,
            'amount' => $payload['amount'],
            'description' => $payload['description'],
            'transaction_date' => $transactionDate instanceof Carbon
                ? $transactionDate->toDateString()
                : (string) $transactionDate,
            'status' => TransactionStatus::COMPLETED,
            'category_id' => $type === TransactionType::TRANSFER->value ? null : $payload['category_id'],
            'source_account_id' => $this->resolveSourceAccountId($type, $payload),
            'destination_account_id' => $this->resolveDestinationAccountId($type, $payload),
            'source_message_id' => $sourceMessageId,
            'voided_at' => null,
            'void_reason' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveSourceAccountId(string $type, array $payload): ?int
    {
        return match ($type) {
            TransactionType::EXPENSE->value => (int) $payload['account_id'],
            TransactionType::TRANSFER->value => (int) $payload['source_account_id'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDestinationAccountId(string $type, array $payload): ?int
    {
        return match ($type) {
            TransactionType::INCOME->value => (int) $payload['account_id'],
            TransactionType::TRANSFER->value => (int) $payload['destination_account_id'],
            default => null,
        };
    }
}
