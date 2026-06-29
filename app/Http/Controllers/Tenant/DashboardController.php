<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\AccountBalanceService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {
    }

    public function __invoke(): View
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();
        $tenantId = $user->tenant_id;
        $now = now()->timezone($user->tenant->timezone);
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $previousMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $transactionBaseQuery = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value);

        if ($user->role === UserRole::MEMBER) {
            $transactionBaseQuery->where('recorded_by_user_id', $user->id);
        }

        $accountBalances = $this->accountBalanceService->balancesForTenant($tenantId);
        $totalBalance = (float) DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id'])
            ->sum(fn (object $account): float => $accountBalances[(int) $account->id] ?? 0.0);
        $previousTotalBalance = $this->totalBalanceUntil($tenantId, $previousMonthEnd);

        $monthlyIncome = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $previousMonthlyIncome = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        $monthlyExpense = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $previousMonthlyExpense = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        $monthlyTransactionCount = (int) (clone $transactionBaseQuery)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->count();
        $previousMonthlyTransactionCount = (int) (clone $transactionBaseQuery)
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $recentTransactions = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.status', TransactionStatus::COMPLETED->value)
            ->when(
                $user->role === UserRole::MEMBER,
                fn ($query) => $query->where('transactions.recorded_by_user_id', $user->id)
            )
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(3)
            ->get([
                'transactions.id',
                'transactions.transaction_date',
                'transactions.description',
                'transactions.type',
                'transactions.amount',
                'categories.name as category_name',
            ])
            ->map(fn (object $transaction): array => [
                'id' => (int) $transaction->id,
                'date' => Carbon::parse($transaction->transaction_date)->format('d M Y'),
                'description' => $transaction->description ?: self::defaultTransactionDescription($transaction->type),
                'meta' => 'ID: TRX-'.str_pad((string) $transaction->id, 4, '0', STR_PAD_LEFT),
                'type_key' => $transaction->type,
                'icon_label' => self::transactionIconLabel((string) $transaction->type),
                'category' => $transaction->category_name ?: 'Tanpa kategori',
                'amount' => self::formatSignedCurrency((string) $transaction->type, (float) $transaction->amount),
            ])
            ->all();

        $weeklyExpenseRangeStart = $now->copy()->subDays(6)->toDateString();
        $weeklyExpenseRangeEnd = $now->toDateString();
        $rawWeeklyExpenses = (clone $transactionBaseQuery)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('transaction_date', [$weeklyExpenseRangeStart, $weeklyExpenseRangeEnd])
            ->selectRaw('transaction_date, SUM(amount) as total')
            ->groupBy('transaction_date')
            ->pluck('total', 'transaction_date');

        $dayMap = [
            'Mon' => 'Sen',
            'Tue' => 'Sel',
            'Wed' => 'Rab',
            'Thu' => 'Kam',
            'Fri' => 'Jum',
            'Sat' => 'Sab',
            'Sun' => 'Min',
        ];

        $spendingChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $key = $day->toDateString();
            $spendingChart[] = [
                'label' => $dayMap[$day->format('D')] ?? $day->format('D'),
                'value' => (float) ($rawWeeklyExpenses[$key] ?? 0.0),
            ];
        }

        $maxSpending = collect($spendingChart)->max('value') ?: 0.0;
        $spendingChart = array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'value' => $item['value'],
                'is_peak' => $maxSpending > 0 && $item['value'] === $maxSpending,
                'height' => $maxSpending > 0 ? max(18, (int) round(($item['value'] / $maxSpending) * 100)) : 18,
                'tooltip' => self::formatCompactCurrency((float) $item['value']),
            ],
            $spendingChart
        );

        $supportLink = Route::has('tenant.support')
            ? route('tenant.support')
            : route('tenant.dashboard');

        $accountRows = DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(2)
            ->get(['id', 'name', 'account_type', 'is_active', 'is_default'])
            ->map(fn (object $account): array => [
                'id' => (int) $account->id,
                'name' => $account->name,
                'type' => self::accountTypeLabel((string) $account->account_type),
                'balance' => self::formatCurrency($accountBalances[(int) $account->id] ?? 0.0),
                'meta' => self::accountMeta((int) $account->id, (string) $account->account_type),
                'icon_asset' => self::accountIconAsset((string) $account->account_type),
                'is_active' => (bool) $account->is_active,
                'is_default' => (bool) $account->is_default,
            ])
            ->all();

        $balanceTrend = self::comparisonMeta($totalBalance, $previousTotalBalance);
        $incomeTrend = self::comparisonMeta($monthlyIncome, $previousMonthlyIncome);
        $expenseTrend = self::comparisonMeta($monthlyExpense, $previousMonthlyExpense, true);
        $transactionTrend = self::comparisonMeta((float) $monthlyTransactionCount, (float) $previousMonthlyTransactionCount);
        $transactionProgress = self::comparisonProgress($monthlyTransactionCount, $previousMonthlyTransactionCount);

        $ownerQuickActions = [
            [
                'label' => 'Tambah Anggota',
                'href' => route('tenant.members.index', ['create' => 1]),
                'icon' => asset('images/figma/accounts/members.svg'),
                'is_primary' => true,
            ],
            [
                'label' => 'Tambah Akun',
                'href' => route('tenant.accounts.index', ['create' => 1]),
                'icon' => asset('images/figma/accounts/accounts.svg'),
                'is_primary' => false,
            ],
            [
                'label' => 'Tambah Kategori',
                'href' => route('tenant.categories.index', ['create' => 1]),
                'icon' => asset('images/figma/accounts/categories.svg'),
                'is_primary' => false,
            ],
        ];

        $memberQuickActions = [
            [
                'label' => 'Lihat Transaksi',
                'href' => route('tenant.transactions.index'),
                'icon' => asset('images/figma/accounts/transactions.svg'),
                'is_primary' => true,
            ],
            [
                'label' => 'Profil Saya',
                'href' => route('tenant.profile.show'),
                'icon' => asset('images/figma/accounts/members.svg'),
                'is_primary' => false,
            ],
        ];

        return view('tenant.dashboard', [
            'page' => [
                'title' => '',
                'description' => '',
                'eyebrow' => strtoupper($user->role->value),
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Cari akun...',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'kpis' => [
                [
                    'label' => 'Total Saldo',
                    'icon' => asset('images/icon-wallet.svg'),
                    'value_prefix' => 'Rp',
                    'value_main' => number_format($totalBalance, 0, ',', '.'),
                    'note' => $balanceTrend['text'],
                    'note_tone' => $balanceTrend['tone'],
                    'tone' => 'is-saldo',
                ],
                [
                    'label' => 'Pemasukan Bulan Ini',
                    'icon' => asset('images/icon-income.png'),
                    'value_prefix' => 'Rp',
                    'value_main' => number_format($monthlyIncome, 0, ',', '.'),
                    'note' => $incomeTrend['text'],
                    'note_tone' => $incomeTrend['tone'],
                    'tone' => 'is-income',
                ],
                [
                    'label' => 'Pengeluaran Bulan Ini',
                    'icon' => asset('images/icon-expanse.png'),
                    'value_prefix' => 'Rp',
                    'value_main' => number_format($monthlyExpense, 0, ',', '.'),
                    'note' => $expenseTrend['text'],
                    'note_tone' => $expenseTrend['tone'],
                    'tone' => 'is-expense',
                ],
                [
                    'label' => 'Transaksi Bulan Ini',
                    'icon' => asset('images/icon-paper.svg'),
                    'value_prefix' => null,
                    'value_main' => number_format($monthlyTransactionCount, 0, ',', '.'),
                    'note' => $transactionTrend['text'],
                    'note_tone' => $transactionTrend['tone'],
                    'progress' => $transactionProgress,
                    'tone' => 'is-transaction',
                ],
            ],
            'transactionRows' => $recentTransactions,
            'accountRows' => $accountRows,
            'quickActions' => $user->role === UserRole::OWNER
                ? $ownerQuickActions
                : $memberQuickActions,
            'spendingChart' => $spendingChart,
            'supportLink' => $supportLink,
            'supportCopy' => 'Tim kami siap membantu Anda mengelola keuangan '.($user->tenant->name ?: 'tenant').'.',
        ]);
    }

    private function totalBalanceUntil(int $tenantId, string $endDate): float
    {
        $openingBalance = (float) DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->sum('opening_balance');

        $incomingBalance = (float) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->whereIn('type', [TransactionType::INCOME->value, TransactionType::TRANSFER->value])
            ->whereNotNull('destination_account_id')
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $outgoingBalance = (float) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->whereIn('type', [TransactionType::EXPENSE->value, TransactionType::TRANSFER->value])
            ->whereNotNull('source_account_id')
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        return $openingBalance + $incomingBalance - $outgoingBalance;
    }

    private static function defaultTransactionDescription(string $type): string
    {
        return match ($type) {
            TransactionType::INCOME->value => 'Pemasukan baru',
            TransactionType::EXPENSE->value => 'Pengeluaran baru',
            TransactionType::TRANSFER->value => 'Transfer antar akun',
            default => 'Aktivitas transaksi',
        };
    }

    private static function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private static function formatSignedCurrency(string $type, float $amount): string
    {
        $formatted = number_format($amount, 0, ',', '.');

        return match ($type) {
            TransactionType::INCOME->value => '+ Rp '.$formatted,
            TransactionType::EXPENSE->value => '- Rp '.$formatted,
            default => 'Rp '.$formatted,
        };
    }

    private static function formatCompactCurrency(float $amount): string
    {
        if ($amount >= 1000000000) {
            return 'Rp '.number_format($amount / 1000000000, 1, ',', '.').' M';
        }

        if ($amount >= 1000000) {
            return 'Rp '.number_format($amount / 1000000, 1, ',', '.').' jt';
        }

        if ($amount >= 1000) {
            return 'Rp '.number_format($amount / 1000, 1, ',', '.').' rb';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * @return array{text: string, tone: string}
     */
    private static function comparisonMeta(float $current, float $previous, bool $positiveWhenLower = false): array
    {
        if (abs($current - $previous) < 0.01) {
            return [
                'text' => 'Sama dengan bulan lalu',
                'tone' => 'neutral',
            ];
        }

        if ($previous <= 0.0) {
            return [
                'text' => $current > 0 ? 'Naik 100% dari bulan lalu' : 'Belum ada data bulan lalu',
                'tone' => $positiveWhenLower ? 'negative' : 'positive',
            ];
        }

        $isIncrease = $current > $previous;
        $percentage = (int) round((abs($current - $previous) / $previous) * 100);
        $isPositive = $positiveWhenLower ? ! $isIncrease : $isIncrease;

        return [
            'text' => ($isIncrease ? 'Naik ' : 'Turun ').$percentage.'% dari bulan lalu',
            'tone' => $isPositive ? 'positive' : 'negative',
        ];
    }

    private static function comparisonProgress(int $current, int $previous): int
    {
        if ($current <= 0) {
            return 0;
        }

        if ($previous <= 0) {
            return 100;
        }

        return max(12, min(100, (int) round(($current / $previous) * 100)));
    }

    private static function transactionIconLabel(string $type): string
    {
        return match ($type) {
            TransactionType::INCOME->value => 'IN',
            TransactionType::EXPENSE->value => 'EX',
            TransactionType::TRANSFER->value => 'TR',
            default => 'TX',
        };
    }

    private static function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'cash' => 'Cash',
            'bank' => 'Bank',
            'e_wallet' => 'E-Wallet',
            default => strtoupper(str_replace('_', '-', $type)),
        };
    }

    private static function accountMeta(int $accountId, string $type): string
    {
        $suffix = str_pad((string) $accountId, 4, '0', STR_PAD_LEFT);

        return match ($type) {
            'bank' => '***** '.$suffix,
            'e_wallet' => '08** **** '.$suffix,
            'cash' => 'Laci Kas '.$suffix,
            default => 'Akun '.$suffix,
        };
    }

    private static function accountIconAsset(string $type): string
    {
        return match ($type) {
            'bank' => 'bank-icon.svg',
            'e_wallet' => 'ewallet-icon.svg',
            'cash' => 'cash-icon.svg',
            default => 'bank-icon-muted.svg',
        };
    }
}
