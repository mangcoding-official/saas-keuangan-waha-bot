<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CategoryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Requests\VoidTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TenantUser;
use App\Models\Transaction;
use App\Services\TransactionManagementService;
use App\Support\CategoryVisualCatalog;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionManagementService $transactionManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();
        $tenantId = $user->tenant_id;
        $now = now()->timezone($user->tenant->timezone);
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $previousMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $baseQuery = Transaction::query()
            ->with(['recorder', 'category', 'sourceAccount', 'destinationAccount', 'attachments'])
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.status', TransactionStatus::COMPLETED->value);

        if ($user->role === UserRole::MEMBER) {
            $baseQuery->where('transactions.recorded_by_user_id', $user->id);
        }

        $currentMonthSummary = [
            'total' => (float) (clone $baseQuery)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'income' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::INCOME->value)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'expense' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::EXPENSE->value)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'transfer' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::TRANSFER->value)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('amount'),
        ];
        $previousMonthSummary = [
            'total' => (float) (clone $baseQuery)
                ->whereBetween('transactions.transaction_date', [$previousMonthStart, $previousMonthEnd])
                ->sum('amount'),
            'income' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::INCOME->value)
                ->whereBetween('transactions.transaction_date', [$previousMonthStart, $previousMonthEnd])
                ->sum('amount'),
            'expense' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::EXPENSE->value)
                ->whereBetween('transactions.transaction_date', [$previousMonthStart, $previousMonthEnd])
                ->sum('amount'),
            'transfer' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::TRANSFER->value)
                ->whereBetween('transactions.transaction_date', [$previousMonthStart, $previousMonthEnd])
                ->sum('amount'),
        ];
        $summaryCards = [
            [
                'label' => 'Total Transaksi',
                'value' => $this->formatCurrency($currentMonthSummary['total']),
                'icon' => asset('images/icon-wallet.svg'),
                'icon_alt' => 'Total transaksi',
                'tone' => 'total',
                'change' => $this->formatTrend($currentMonthSummary['total'], $previousMonthSummary['total']),
            ],
            [
                'label' => 'Pemasukan',
                'value' => $this->formatCurrency($currentMonthSummary['income']),
                'icon' => asset('images/icon-arrow-up.svg'),
                'icon_alt' => 'Pemasukan',
                'tone' => 'income',
                'change' => $this->formatTrend($currentMonthSummary['income'], $previousMonthSummary['income']),
            ],
            [
                'label' => 'Pengeluaran',
                'value' => $this->formatCurrency($currentMonthSummary['expense']),
                'icon' => asset('images/icon-arrow-down.svg'),
                'icon_alt' => 'Pengeluaran',
                'tone' => 'expense',
                'change' => $this->formatTrend($currentMonthSummary['expense'], $previousMonthSummary['expense']),
            ],
            [
                'label' => 'Transfer',
                'value' => $this->formatCurrency($currentMonthSummary['transfer']),
                'icon' => asset('images/icon-arrow-transfer.svg'),
                'icon_alt' => 'Transfer',
                'tone' => 'transfer',
                'change' => 'Bulan ini',
            ],
        ];

        $filteredQuery = $this->applyFilters((clone $baseQuery), $request);
        $transactionPaginator = $filteredQuery
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
        $transactions = $transactionPaginator->getCollection()
            ->map(fn (Transaction $transaction): array => $this->mapTransaction($transaction, $user))
            ->all();
        $selectedTransaction = $this->resolveSelectedTransaction($request, $user, $baseQuery);
        $editingTransaction = $this->resolveEditingTransaction($request, $user, $baseQuery);
        $activeAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
            ->all();
        $incomeCategories = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('type', CategoryType::INCOME->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
            ->all();
        $expenseCategories = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('type', CategoryType::EXPENSE->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
            ->all();
        $recorders = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TenantUser $tenantUser): array => ['id' => $tenantUser->id, 'name' => $tenantUser->name])
            ->all();
        $transactionTypes = [
            ['value' => '', 'label' => 'Semua Tipe'],
            ['value' => TransactionType::INCOME->value, 'label' => 'Pemasukan'],
            ['value' => TransactionType::EXPENSE->value, 'label' => 'Pengeluaran'],
            ['value' => TransactionType::TRANSFER->value, 'label' => 'Transfer'],
        ];

        return view('tenant.transactions.index', [
            'page' => [
                'title' => null,
                'description' => null,
                'eyebrow' => $user->role === UserRole::OWNER ? 'Workspace' : 'Member',
            ],
            'toolbar' => [
                'search_label' => 'Cari transaksi',
                'search_placeholder' => 'Cari transaksi...',
                'search_action' => route('tenant.transactions.index'),
                'search_name' => 'search',
                'search_value' => (string) $request->query('search', ''),
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'summaryCards' => $summaryCards,
            'transactions' => $transactions,
            'transactionPaginator' => $transactionPaginator,
            'selectedTransaction' => $selectedTransaction,
            'editingTransaction' => $editingTransaction,
            'isCreateModal' => $request->boolean('create') && $user->role === UserRole::OWNER,
            'activeAccounts' => $activeAccounts,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'recorders' => $recorders,
            'transactionTypes' => $transactionTypes,
            'activeFilters' => [
                'period' => (string) $request->query('period', 'this_month'),
                'type' => (string) $request->query('type', ''),
                'category_id' => (string) $request->query('category_id', ''),
                'account_id' => (string) $request->query('account_id', ''),
                'recorder_id' => (string) $request->query('recorder_id', ''),
                'search' => (string) $request->query('search', ''),
            ],
            'usageExamples' => [
                'masuk 15000 bonus',
                'keluar 20rb makan',
                'masuk 1,5 juta gaji 15 juni',
                'keluar 20rb makan 15 juni',
                'transfer 50rb dari cash ke bca',
            ],
        ]);
    }

    public function update(UpdateTransactionRequest $request, int $transactionId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $transaction = $this->findOwnerTransaction($owner, $transactionId);
        $updated = $this->transactionManagementService->update($owner, $transaction, $request->validated());

        return to_route('tenant.transactions.index', ['show' => $updated->id])->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Transaksi diperbarui',
            'message' => 'Perubahan transaksi langsung tercatat di audit log tenant.',
        ]);
    }

    public function void(VoidTransactionRequest $request, int $transactionId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $transaction = $this->findOwnerTransaction($owner, $transactionId);
        $voided = $this->transactionManagementService->void($owner, $transaction, (string) $request->validated('void_reason'));

        return to_route('tenant.transactions.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Transaksi di-void',
            'message' => 'Transaction #'.$voided->id.' dipindahkan dari daftar completed dan alasannya tercatat di audit log.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTransaction(Transaction $transaction, TenantUser $user): array
    {
        $recorderName = $transaction->recorder?->name ?: 'System';
        $recorderInitials = collect(explode(' ', $recorderName))
            ->filter()
            ->map(fn (string $word): string => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return [
            'id' => $transaction->id,
            'date' => Carbon::parse($transaction->transaction_date)->format('d M Y'),
            'date_short' => Carbon::parse($transaction->transaction_date)->format('d M'),
            'date_year' => Carbon::parse($transaction->transaction_date)->format('Y'),
            'transaction_date_value' => $transaction->transaction_date?->toDateString(),
            'logged_time' => Carbon::parse($transaction->created_at)->timezone($user->tenant->timezone)->format('H:i'),
            'logged_at' => Carbon::parse($transaction->created_at)->timezone($user->tenant->timezone)->format('d M Y H:i'),
            'description' => $transaction->description ?: '-',
            'description_value' => $transaction->description,
            'reference' => 'TRX #'.str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT),
            'type' => strtoupper($transaction->type->value),
            'type_value' => $transaction->type->value,
            'amount' => 'Rp '.number_format((float) $transaction->amount, 0, ',', '.'),
            'amount_value' => number_format((float) $transaction->amount, 2, '.', ''),
            'recorder' => $recorderName,
            'recorder_initials' => $recorderInitials !== '' ? $recorderInitials : 'SY',
            'category' => $transaction->category?->name ?: '-',
            'category_id' => $transaction->category_id,
            'category_visual_asset' => $this->resolveCategoryVisualAsset($transaction),
            'source_account' => $transaction->sourceAccount?->name ?: '-',
            'source_account_id' => $transaction->source_account_id,
            'destination_account' => $transaction->destinationAccount?->name ?: '-',
            'destination_account_id' => $transaction->destination_account_id,
            'status_label' => 'Berhasil',
            'status_key' => 'success',
            'attachments' => $transaction->attachments
                ->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'url' => route('tenant.attachments.show', $attachment->id),
                    'name' => $attachment->original_file_name ?: 'Bukti transaksi #'.$attachment->id,
                    'mime_type' => $attachment->mime_type,
                    'size' => number_format($attachment->file_size / 1024, 1, ',', '.').' KB',
                    'dimensions' => $attachment->width && $attachment->height
                        ? $attachment->width.' x '.$attachment->height.' px'
                        : null,
                ])
                ->all(),
        ];
    }

    private function resolveEditingTransaction(Request $request, TenantUser $user, Builder $baseQuery): ?array
    {
        if ($user->role !== UserRole::OWNER || $request->integer('edit') <= 0) {
            return null;
        }

        $transaction = (clone $baseQuery)->find($request->integer('edit'));

        return $transaction ? $this->mapTransaction($transaction, $user) : null;
    }

    private function resolveSelectedTransaction(Request $request, TenantUser $user, Builder $baseQuery): ?array
    {
        if ($request->integer('show') <= 0) {
            return null;
        }

        $transaction = (clone $baseQuery)->find($request->integer('show'));

        return $transaction ? $this->mapTransaction($transaction, $user) : null;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('type')) {
            $query->where('transactions.type', (string) $request->query('type'));
        }

        if ($request->filled('category_id')) {
            $query->where('transactions.category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('account_id')) {
            $accountId = (int) $request->query('account_id');
            $query->where(function (Builder $builder) use ($accountId): void {
                $builder
                    ->where('transactions.source_account_id', $accountId)
                    ->orWhere('transactions.destination_account_id', $accountId);
            });
        }

        if ($request->filled('recorder_id')) {
            $query->where('transactions.recorded_by_user_id', (int) $request->query('recorder_id'));
        }

        $period = (string) $request->query('period', 'this_month');
        if ($period === 'last_30_days') {
            $query->whereDate('transactions.transaction_date', '>=', now()->subDays(30)->toDateString());
        } elseif ($period === 'this_month') {
            $query->whereBetween('transactions.transaction_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('transactions.description', 'like', '%'.$search.'%')
                    ->orWhereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('recorder', fn (Builder $recorderQuery): Builder => $recorderQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query;
    }

    private function findOwnerTransaction(TenantUser $owner, int $transactionId): Transaction
    {
        return Transaction::query()
            ->where('tenant_id', $owner->tenant_id)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->findOrFail($transactionId);
    }

    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function formatTrend(float $currentValue, float $previousValue): string
    {
        if ($previousValue <= 0.0) {
            return $currentValue > 0.0 ? 'Baru' : '0%';
        }

        $change = (($currentValue - $previousValue) / $previousValue) * 100;
        $prefix = $change > 0 ? '+' : '';

        return $prefix.number_format($change, 1, ',', '').'%';
    }

    private function resolveCategoryVisualAsset(Transaction $transaction): ?string
    {
        if (! $transaction->category) {
            return null;
        }

        $preset = CategoryVisualCatalog::findPreset($transaction->category->visual_preset_key);

        return $preset ? asset($preset['asset_path']) : null;
    }
}
