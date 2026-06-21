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
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
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

        $baseQuery = Transaction::query()
            ->with(['recorder', 'category', 'sourceAccount', 'destinationAccount'])
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.status', TransactionStatus::COMPLETED->value);

        if ($user->role === UserRole::MEMBER) {
            $baseQuery->where('transactions.recorded_by_user_id', $user->id);
        }

        $summary = [
            'total' => (clone $baseQuery)->count('transactions.id'),
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

        $transactions = (clone $baseQuery)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Transaction $transaction): array => $this->mapTransaction($transaction, $user))
            ->all();

        $selectedTransaction = collect($transactions)->firstWhere('id', $request->integer('show'));
        $editingTransaction = $this->resolveEditingTransaction($request, $user, $selectedTransaction);
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

        return view('tenant.transactions.index', [
            'page' => [
                'title' => 'Transactions',
                'description' => $user->role === UserRole::OWNER
                    ? 'Owner memonitor seluruh transaksi tenant dan memastikan format pesan WA tersimpan rapi.'
                    : 'Member melihat transaksi yang dia catat dari WhatsApp dan referensi format cepat.',
                'eyebrow' => $user->role === UserRole::OWNER ? 'Tenant Module' : 'Member Workspace',
            ],
            'toolbar' => [
                'search_label' => 'Cari transaksi atau kategori',
                'search_placeholder' => 'Search transactions or categories',
                'secondary_action' => [
                    'label' => 'Back to overview',
                    'href' => route('tenant.dashboard'),
                    'variant' => 'secondary',
                ],
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'summary' => $summary,
            'transactions' => $transactions,
            'selectedTransaction' => $selectedTransaction,
            'editingTransaction' => $editingTransaction,
            'activeAccounts' => $activeAccounts,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'usageExamples' => [
                'masuk 15000 bonus',
                'keluar 20rb makan',
                'masuk 1,5 juta gaji 15 juni',
                'keluar 20rb makan 15 juni',
                'transfer 50rb dari cash default ke bca operasional',
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
        return [
            'id' => $transaction->id,
            'date' => Carbon::parse($transaction->transaction_date)->format('d M Y'),
            'transaction_date_value' => $transaction->transaction_date?->toDateString(),
            'logged_at' => Carbon::parse($transaction->created_at)->timezone($user->tenant->timezone)->format('d M Y H:i'),
            'description' => $transaction->description ?: '-',
            'description_value' => $transaction->description,
            'type' => strtoupper($transaction->type->value),
            'type_value' => $transaction->type->value,
            'amount' => 'Rp '.number_format((float) $transaction->amount, 0, ',', '.'),
            'amount_value' => number_format((float) $transaction->amount, 2, '.', ''),
            'recorder' => $transaction->recorder?->name ?: 'System',
            'category' => $transaction->category?->name ?: '-',
            'category_id' => $transaction->category_id,
            'source_account' => $transaction->sourceAccount?->name ?: '-',
            'source_account_id' => $transaction->source_account_id,
            'destination_account' => $transaction->destinationAccount?->name ?: '-',
            'destination_account_id' => $transaction->destination_account_id,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $selectedTransaction
     * @return array<string, mixed>|null
     */
    private function resolveEditingTransaction(Request $request, TenantUser $user, ?array $selectedTransaction): ?array
    {
        if ($user->role !== UserRole::OWNER || ! $selectedTransaction) {
            return null;
        }

        return $request->integer('edit') === $selectedTransaction['id'] ? $selectedTransaction : null;
    }

    private function findOwnerTransaction(TenantUser $owner, int $transactionId): Transaction
    {
        return Transaction::query()
            ->where('tenant_id', $owner->tenant_id)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->findOrFail($transactionId);
    }
}
