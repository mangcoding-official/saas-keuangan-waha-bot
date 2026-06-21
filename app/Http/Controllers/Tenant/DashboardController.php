<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\AccountBalanceService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $transactionBaseQuery = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', TransactionStatus::COMPLETED->value);

        if ($user->role === UserRole::MEMBER) {
            $transactionBaseQuery->where('recorded_by_user_id', $user->id);
        }

        $latestActivationCode = DB::table('activation_codes')
            ->where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->latest('created_at')
            ->first(['code_last4', 'expires_at']);

        $activeAccountsCount = (int) DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $accountBalances = $this->accountBalanceService->balancesForTenant($tenantId);
        $totalBalance = (float) DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id'])
            ->sum(fn (object $account): float => $accountBalances[(int) $account->id] ?? 0.0);

        $monthlyIncome = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyExpense = (float) (clone $transactionBaseQuery)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyTransactionCount = (int) (clone $transactionBaseQuery)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->count();

        $membersTotal = (int) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->count();

        $membersActive = (int) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_status', UserStatus::ACTIVE->value)
            ->count();

        $membersPending = (int) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('verification_status', VerificationStatus::PENDING_VERIFICATION->value)
            ->count();

        $membersInactive = (int) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_status', UserStatus::INACTIVE->value)
            ->count();

        $recentTransactions = DB::table('transactions')
            ->leftJoin('tenant_users', 'tenant_users.id', '=', 'transactions.recorded_by_user_id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.status', TransactionStatus::COMPLETED->value)
            ->when(
                $user->role === UserRole::MEMBER,
                fn ($query) => $query->where('transactions.recorded_by_user_id', $user->id)
            )
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(4)
            ->get([
                'transactions.transaction_date',
                'transactions.created_at',
                'transactions.description',
                'transactions.type',
                'transactions.amount',
                'tenant_users.name as recorder_name',
            ])
            ->map(fn (object $transaction): array => [
                'date' => Carbon::parse($transaction->transaction_date)->format('d M'),
                'flow_stamp' => Carbon::parse($transaction->created_at)->timezone($user->tenant->timezone)->format('d M H:i'),
                'description' => $transaction->description ?: self::defaultTransactionDescription($transaction->type),
                'recorder' => $transaction->recorder_name ?: 'System',
                'type' => ucfirst($transaction->type),
                'amount' => self::formatCurrency((float) $transaction->amount),
            ])
            ->all();

        $transactionFlow = array_slice(array_map(
            fn (array $transaction): array => [
                'stamp' => $transaction['flow_stamp'],
                'summary' => $transaction['description'],
                'amount' => $transaction['amount'],
            ],
            $recentTransactions
        ), 0, 3);

        $accountRows = DB::table('accounts')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'is_active', 'is_default'])
            ->map(fn (object $account): array => [
                'name' => $account->name,
                'balance' => self::formatCurrency($accountBalances[(int) $account->id] ?? 0.0),
                'status' => $account->is_active ? 'Active' : 'Inactive',
                'note' => $account->is_default ? 'Default account' : 'Other account',
            ])
            ->all();

        $attentionItems = $user->role === UserRole::OWNER
            ? $membersPending + $membersInactive + ($user->verification_status === VerificationStatus::PENDING_VERIFICATION ? 1 : 0)
            : ($user->verification_status === VerificationStatus::PENDING_VERIFICATION ? 1 : 0);
        $attentionNotes = [];

        if ($user->role === UserRole::OWNER && $membersPending > 0) {
            $attentionNotes[] = $membersPending.' pending verification';
        }

        if ($user->role === UserRole::OWNER && $membersInactive > 0) {
            $attentionNotes[] = $membersInactive.' inactive';
        }

        if ($user->verification_status === VerificationStatus::PENDING_VERIFICATION) {
            $attentionNotes[] = 'owner belum verified';
        }

        $alerts = [];

        if ($user->role === UserRole::OWNER && $membersPending > 0) {
            $alerts[] = $membersPending.' member masih menunggu verifikasi.';
        }

        if ($latestActivationCode && $user->verification_status === VerificationStatus::PENDING_VERIFICATION) {
            $alerts[] = 'Kode aktivasi owner aktif sampai '.Carbon::parse($latestActivationCode->expires_at)->timezone($user->tenant->timezone)->format('d M Y H:i').'.';
        }

        if ($monthlyExpense > $monthlyIncome && $monthlyExpense > 0) {
            $alerts[] = 'Pengeluaran bulan ini lebih tinggi dari pemasukan.';
        }

        if ($activeAccountsCount === 0) {
            $alerts[] = 'Belum ada akun aktif untuk menerima transaksi.';
        }

        if ($recentTransactions === []) {
            $alerts[] = $user->role === UserRole::OWNER
                ? 'Belum ada transaksi tercatat. Onboarding tenant masih di tahap awal.'
                : 'Belum ada transaksi yang kamu catat dari WhatsApp.';
        }

        if ($alerts === []) {
            $alerts[] = 'Tidak ada alert operasional untuk tenant ini.';
        }

        return view('tenant.dashboard', [
            'page' => [
                'title' => 'Overview',
                'description' => '',
                'eyebrow' => strtoupper($user->role->value),
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Search transaction, account, category, or member',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'kpis' => [
                [
                    'label' => $user->role === UserRole::OWNER ? 'Saldo total' : 'Transaksi bulan ini',
                    'value' => $user->role === UserRole::OWNER
                        ? self::formatCompactCurrency($totalBalance)
                        : (string) $monthlyTransactionCount,
                    'note' => $user->role === UserRole::OWNER
                        ? $activeAccountsCount.' akun aktif'
                        : 'Jumlah transaksi yang kamu catat bulan berjalan',
                    'tone' => 'neutral',
                ],
                [
                    'label' => 'Pemasukan bulan ini',
                    'value' => self::formatCompactCurrency($monthlyIncome),
                    'note' => 'Total pemasukan dari semua transaksi tipe income bulan berjalan',
                    'tone' => 'neutral',
                ],
                [
                    'label' => 'Pengeluaran bulan ini',
                    'value' => self::formatCompactCurrency($monthlyExpense),
                    'note' => $user->role === UserRole::OWNER
                        ? 'Total pengeluaran dari semua transaksi tipe expense bulan berjalan'
                        : 'Pengeluaran pribadi yang kamu catat',
                    'tone' => 'neutral',
                ],
                [
                    'label' => 'Butuh perhatian',
                    'value' => $attentionItems.' item',
                    'note' => $attentionNotes === [] ? 'Tidak ada antrian' : implode(', ', $attentionNotes),
                    'tone' => $attentionItems > 0 ? 'alert' : 'neutral',
                ],
            ],
            'transactionFlow' => $transactionFlow,
            'transactionRows' => $recentTransactions,
            'accountRows' => $accountRows,
            'memberStatus' => $user->role === UserRole::OWNER
                ? [
                    ['label' => 'Total member', 'value' => (string) $membersTotal],
                    ['label' => 'Active', 'value' => (string) $membersActive],
                    ['label' => 'Pending verification', 'value' => (string) $membersPending],
                    ['label' => 'Inactive', 'value' => (string) $membersInactive],
                ]
                : [
                    ['label' => 'Role', 'value' => strtoupper($user->role->value)],
                    ['label' => 'Status', 'value' => $user->user_status->value],
                    ['label' => 'Verification', 'value' => $user->verification_status->value],
                    ['label' => 'Nomor WA', 'value' => $user->whatsapp_number],
                ],
            'pendingBadge' => $user->role === UserRole::OWNER
                ? ($membersPending > 0 ? $membersPending.' pending verification' : null)
                : ($user->verification_status === VerificationStatus::PENDING_VERIFICATION ? 'Nomor kamu masih pending verification' : null),
            'quickActions' => $user->role === UserRole::OWNER
                ? [
                    ['label' => 'Tambah member', 'href' => route('tenant.members.index')],
                    ['label' => 'Catat transaksi', 'href' => route('tenant.transactions.index')],
                    ['label' => 'Kelola akun', 'href' => route('tenant.accounts.index')],
                    ['label' => 'Review audit log', 'href' => route('tenant.audit.index')],
                ]
                : [
                    ['label' => 'Lihat transaksi saya', 'href' => route('tenant.transactions.index')],
                    ['label' => 'Open profile', 'href' => route('tenant.profile.show')],
                ],
            'alerts' => $alerts,
        ]);
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
}
