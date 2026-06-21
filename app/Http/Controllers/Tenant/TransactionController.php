<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(): View
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();
        $tenantId = $user->tenant_id;
        $now = now()->timezone($user->tenant->timezone);
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $baseQuery = DB::table('transactions')
            ->leftJoin('tenant_users', 'tenant_users.id', '=', 'transactions.recorded_by_user_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts as source_accounts', 'source_accounts.id', '=', 'transactions.source_account_id')
            ->leftJoin('accounts as destination_accounts', 'destination_accounts.id', '=', 'transactions.destination_account_id')
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
                ->sum('transactions.amount'),
            'expense' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::EXPENSE->value)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('transactions.amount'),
            'transfer' => (float) (clone $baseQuery)
                ->where('transactions.type', TransactionType::TRANSFER->value)
                ->whereBetween('transactions.transaction_date', [$monthStart, $monthEnd])
                ->sum('transactions.amount'),
        ];

        $transactions = (clone $baseQuery)
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->get([
                'transactions.id',
                'transactions.transaction_date',
                'transactions.created_at',
                'transactions.description',
                'transactions.type',
                'transactions.amount',
                'tenant_users.name as recorder_name',
                'categories.name as category_name',
                'source_accounts.name as source_account_name',
                'destination_accounts.name as destination_account_name',
            ])
            ->map(fn (object $transaction): array => [
                'id' => (int) $transaction->id,
                'date' => Carbon::parse($transaction->transaction_date)->format('d M Y'),
                'logged_at' => Carbon::parse($transaction->created_at)->timezone($user->tenant->timezone)->format('d M Y H:i'),
                'description' => $transaction->description ?: '-',
                'type' => strtoupper($transaction->type),
                'amount' => 'Rp '.number_format((float) $transaction->amount, 0, ',', '.'),
                'recorder' => $transaction->recorder_name ?: 'System',
                'category' => $transaction->category_name ?: '-',
                'source_account' => $transaction->source_account_name ?: '-',
                'destination_account' => $transaction->destination_account_name ?: '-',
            ])
            ->all();

        $selectedTransaction = collect($transactions)->firstWhere('id', request()->integer('show'));

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
            'usageExamples' => [
                'masuk 15000 bonus',
                'keluar 20rb makan',
                'masuk 1,5 juta gaji 15 juni',
                'keluar 20rb makan 15 juni',
                'transfer 50rb dari cash default ke bca operasional',
            ],
        ]);
    }
}
