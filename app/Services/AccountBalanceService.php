<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\DB;

class AccountBalanceService
{
    /**
     * @return array<int, float>
     */
    public function balancesForTenant(int $tenantId): array
    {
        $openingBalances = DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->pluck('opening_balance', 'id')
            ->map(fn (mixed $amount): float => (float) $amount)
            ->all();

        $incomingBalances = DB::table('transactions')
            ->selectRaw('destination_account_id as account_id, SUM(amount) as total_amount')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->whereIn('type', [
                TransactionType::INCOME->value,
                TransactionType::TRANSFER->value,
            ])
            ->whereNotNull('destination_account_id')
            ->groupBy('destination_account_id')
            ->pluck('total_amount', 'account_id')
            ->map(fn (mixed $amount): float => (float) $amount)
            ->all();

        $outgoingBalances = DB::table('transactions')
            ->selectRaw('source_account_id as account_id, SUM(amount) as total_amount')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->whereIn('type', [
                TransactionType::EXPENSE->value,
                TransactionType::TRANSFER->value,
            ])
            ->whereNotNull('source_account_id')
            ->groupBy('source_account_id')
            ->pluck('total_amount', 'account_id')
            ->map(fn (mixed $amount): float => (float) $amount)
            ->all();

        $balances = [];

        foreach ($openingBalances as $accountId => $openingBalance) {
            $balances[(int) $accountId] = $openingBalance
                + ($incomingBalances[$accountId] ?? 0.0)
                - ($outgoingBalances[$accountId] ?? 0.0);
        }

        return $balances;
    }
}
