<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\TenantUser;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionManagementService
{
    public function __construct(
        private readonly TenantAuditLogService $tenantAuditLogService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TenantUser $owner, Transaction $transaction, array $payload): Transaction
    {
        if ($transaction->status !== TransactionStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'transaction' => 'Hanya transaksi completed yang bisa diedit.',
            ]);
        }

        return DB::transaction(function () use ($owner, $transaction, $payload): Transaction {
            $beforeSnapshot = $this->snapshot($transaction);
            $transactionType = TransactionType::from((string) ($payload['transaction_type'] ?? $transaction->type->value));
            $accountIds = $this->resolveAccountIds($owner->tenant_id, $transactionType, $payload);
            $categoryId = $this->resolveCategoryId($owner->tenant_id, $transactionType, $payload);

            $transaction->fill([
                'type' => $transactionType,
                'amount' => round((float) $payload['amount'], 2),
                'transaction_date' => (string) $payload['transaction_date'],
                'description' => $this->normalizeNullableText($payload['description'] ?? null),
                'category_id' => $categoryId,
                'source_account_id' => $accountIds['source_account_id'],
                'destination_account_id' => $accountIds['destination_account_id'],
            ])->save();

            $updated = $transaction->fresh(['category', 'sourceAccount', 'destinationAccount']);

            $this->tenantAuditLogService->logTenantUser(
                $owner,
                'transaction',
                $updated->id,
                'transaction_updated',
                $beforeSnapshot,
                $this->snapshot($updated),
            );

            return $updated;
        });
    }

    public function void(TenantUser $owner, Transaction $transaction, string $reason): Transaction
    {
        if ($transaction->status === TransactionStatus::VOID) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi ini sudah void.',
            ]);
        }

        return DB::transaction(function () use ($owner, $transaction, $reason): Transaction {
            $beforeSnapshot = $this->snapshot($transaction);

            $transaction->forceFill([
                'status' => TransactionStatus::VOID,
                'voided_at' => now(),
                'void_reason' => trim($reason),
            ])->save();

            $voided = $transaction->fresh(['category', 'sourceAccount', 'destinationAccount']);

            $this->tenantAuditLogService->logTenantUser(
                $owner,
                'transaction',
                $voided->id,
                'transaction_voided',
                $beforeSnapshot,
                $this->snapshot($voided),
            );

            return $voided;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{source_account_id: int|null, destination_account_id: int|null}
     */
    private function resolveAccountIds(int $tenantId, TransactionType $type, array $payload): array
    {
        $sourceAccountId = isset($payload['source_account_id']) ? (int) $payload['source_account_id'] : null;
        $destinationAccountId = isset($payload['destination_account_id']) ? (int) $payload['destination_account_id'] : null;

        if ($type === TransactionType::INCOME) {
            return [
                'source_account_id' => null,
                'destination_account_id' => $this->findActiveAccount($tenantId, $destinationAccountId, 'destination_account_id')->id,
            ];
        }

        if ($type === TransactionType::EXPENSE) {
            return [
                'source_account_id' => $this->findActiveAccount($tenantId, $sourceAccountId, 'source_account_id')->id,
                'destination_account_id' => null,
            ];
        }

        $sourceAccount = $this->findActiveAccount($tenantId, $sourceAccountId, 'source_account_id');
        $destinationAccount = $this->findActiveAccount($tenantId, $destinationAccountId, 'destination_account_id');

        if ($sourceAccount->id === $destinationAccount->id) {
            throw ValidationException::withMessages([
                'destination_account_id' => 'Akun tujuan transfer harus berbeda dari akun sumber.',
            ]);
        }

        return [
            'source_account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCategoryId(int $tenantId, TransactionType $type, array $payload): ?int
    {
        if ($type === TransactionType::TRANSFER) {
            return null;
        }

        $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;
        $expectedType = $type === TransactionType::INCOME ? CategoryType::INCOME : CategoryType::EXPENSE;

        return $this->findActiveCategory($tenantId, $expectedType, $categoryId)->id;
    }

    private function findActiveAccount(int $tenantId, ?int $accountId, string $field): Account
    {
        if (($accountId ?? 0) <= 0) {
            throw ValidationException::withMessages([
                $field => 'Akun wajib dipilih.',
            ]);
        }

        $account = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                $field => 'Akun tidak ditemukan atau sudah inactive.',
            ]);
        }

        return $account;
    }

    private function findActiveCategory(int $tenantId, CategoryType $type, ?int $categoryId): Category
    {
        if (($categoryId ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'category_id' => 'Kategori wajib dipilih.',
            ]);
        }

        $category = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type->value)
            ->where('is_active', true)
            ->find($categoryId);

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'Kategori tidak ditemukan atau sudah inactive.',
            ]);
        }

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Transaction $transaction): array
    {
        $transaction->loadMissing(['category', 'sourceAccount', 'destinationAccount']);

        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'amount' => (float) $transaction->amount,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
            'description' => $transaction->description,
            'category_id' => $transaction->category_id,
            'category_name' => $transaction->category?->name,
            'source_account_id' => $transaction->source_account_id,
            'source_account_name' => $transaction->sourceAccount?->name,
            'destination_account_id' => $transaction->destination_account_id,
            'destination_account_name' => $transaction->destinationAccount?->name,
            'voided_at' => $transaction->voided_at?->toDateTimeString(),
            'void_reason' => $transaction->void_reason,
        ];
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
