<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceInquiryService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {
    }

    public function replyForTenantUser(TenantUser $tenantUser): string
    {
        $tenant = $tenantUser->tenant;
        $tenantId = $tenantUser->tenant_id;

        $accounts = DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $balances = $this->accountBalanceService->balancesForTenant($tenantId);
        $totalBalance = $accounts->sum(fn (object $account): float => $balances[(int) $account->id] ?? 0.0);

        return $this->buildBalanceReply(
            $tenant?->name ?? 'Tenant',
            (float) $totalBalance,
            $accounts->map(fn (object $account): array => [
                'name' => $account->name,
                'balance' => (float) ($balances[(int) $account->id] ?? 0.0),
                'is_default' => (bool) $account->is_default,
            ])->all(),
        );
    }

    public function latestTransactionsReplyForTenantUser(TenantUser $tenantUser): string
    {
        $tenant = $tenantUser->tenant;
        $tenantId = $tenantUser->tenant_id;
        $timezone = $tenant?->timezone ?? config('app.timezone', 'Asia/Jakarta');

        $latestTransactions = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts as source_accounts', 'source_accounts.id', '=', 'transactions.source_account_id')
            ->leftJoin('accounts as destination_accounts', 'destination_accounts.id', '=', 'transactions.destination_account_id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.status', TransactionStatus::COMPLETED->value)
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(5)
            ->get([
                'transactions.type',
                'transactions.amount',
                'transactions.description',
                'transactions.transaction_date',
                'categories.name as category_name',
                'source_accounts.name as source_account_name',
                'destination_accounts.name as destination_account_name',
            ])
            ->map(fn (object $transaction): array => [
                'date' => Carbon::parse($transaction->transaction_date)->timezone($timezone)->format('d M'),
                'type' => strtoupper((string) $transaction->type),
                'amount' => (float) $transaction->amount,
                'description' => $transaction->description ?: $this->defaultTransactionDescription((string) $transaction->type),
                'category' => $transaction->category_name,
                'source_account' => $transaction->source_account_name,
                'destination_account' => $transaction->destination_account_name,
            ])
            ->all();

        return $this->buildLatestTransactionsReply(
            $tenant?->name ?? 'Tenant',
            $latestTransactions,
        );
    }

    /**
     * @param  array<int, array{name:string,balance:float,is_default:bool}>  $accounts
     */
    public function buildBalanceReply(string $tenantName, float $totalBalance, array $accounts): string
    {
        $lines = [
            'Saldo '.$tenantName,
            'Saldo total: '.$this->formatCurrency($totalBalance),
            '',
            'Saldo per akun:',
        ];

        if ($accounts === []) {
            $lines[] = '- Belum ada akun aktif.';
        } else {
            foreach ($accounts as $account) {
                $defaultSuffix = $account['is_default'] ? ' (default)' : '';
                $lines[] = '- '.$account['name'].$defaultSuffix.': '.$this->formatCurrency($account['balance']);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array{
     *     date:string,
     *     type:string,
     *     amount:float,
     *     description:string,
     *     category:?string,
     *     source_account:?string,
     *     destination_account:?string
     * }>  $latestTransactions
     */
    public function buildLatestTransactionsReply(string $tenantName, array $latestTransactions): string
    {
        $lines = [
            '5 transaksi terakhir '.$tenantName.':',
        ];

        if ($latestTransactions === []) {
            $lines[] = '- Belum ada transaksi tersimpan.';
        } else {
            foreach ($latestTransactions as $transaction) {
                $accountSummary = $this->transactionAccountSummary($transaction);
                $category = $transaction['category'] ? ' | '.$transaction['category'] : '';
                $lines[] = sprintf(
                    '- %s | %s | %s | %s%s%s',
                    $transaction['date'],
                    $transaction['type'],
                    $this->formatCurrency($transaction['amount']),
                    $transaction['description'],
                    $category,
                    $accountSummary !== '' ? ' | '.$accountSummary : ''
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{
     *     type:string,
     *     source_account:?string,
     *     destination_account:?string
     * }  $transaction
     */
    private function transactionAccountSummary(array $transaction): string
    {
        return match (strtolower($transaction['type'])) {
            TransactionType::TRANSFER->value => trim(($transaction['source_account'] ?: '-').' -> '.($transaction['destination_account'] ?: '-')),
            TransactionType::INCOME->value => $transaction['destination_account'] ?: '',
            TransactionType::EXPENSE->value => $transaction['source_account'] ?: '',
            default => '',
        };
    }

    private function defaultTransactionDescription(string $type): string
    {
        return match ($type) {
            TransactionType::INCOME->value => 'Pemasukan baru',
            TransactionType::EXPENSE->value => 'Pengeluaran baru',
            TransactionType::TRANSFER->value => 'Transfer antar akun',
            default => 'Aktivitas transaksi',
        };
    }

    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
