<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\TenantUser;
use App\Services\AccountManagementService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountManagementService $accountManagementService,
    ) {
    }

    public function index(): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $editingAccount = $this->resolveAccountForEdit($owner);

        $accounts = Account::query()
            ->where('tenant_id', $owner->tenant_id)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'account_type' => strtoupper(str_replace('_', '-', $account->account_type->value)),
                'opening_balance' => number_format((float) $account->opening_balance, 2, ',', '.'),
                'is_default' => $account->is_default,
                'is_active' => $account->is_active,
                'updated_at' => Carbon::parse($account->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $activeCount = count(array_filter($accounts, fn (array $account): bool => $account['is_active']));
        $inactiveCount = count($accounts) - $activeCount;
        $defaultAccount = collect($accounts)->firstWhere('is_default', true);

        return view('tenant.accounts.index', [
            'page' => [
                'title' => 'Saldo Accounts',
                'description' => 'Mengelola akun keuangan.',
                'eyebrow' => 'Accounts',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Search account or type',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'editingAccount' => $editingAccount,
            'summary' => [
                'total' => count($accounts),
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'default_name' => $defaultAccount['name'] ?? '-',
            ],
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $account = $this->accountManagementService->create($owner, $request->validated());

        return to_route('tenant.accounts.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Akun berhasil dibuat',
            'message' => $account->name.' siap dipakai sebagai master data tenant.',
        ]);
    }

    public function update(UpdateAccountRequest $request, int $accountId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $account = $this->findAccount($owner, $accountId);
        $updated = $this->accountManagementService->update($owner, $account, $request->validated());

        return to_route('tenant.accounts.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Akun diperbarui',
            'message' => $updated->name.' berhasil diperbarui.',
        ]);
    }

    public function setDefault(int $accountId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $account = $this->findAccount($owner, $accountId);
        $updated = $this->accountManagementService->setDefault($owner, $account);

        return to_route('tenant.accounts.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Default account diperbarui',
            'message' => $updated->name.' sekarang menjadi akun default tenant.',
        ]);
    }

    public function activate(int $accountId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $account = $this->findAccount($owner, $accountId);
        $updated = $this->accountManagementService->activate($owner, $account);

        return to_route('tenant.accounts.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Akun diaktifkan',
            'message' => $updated->name.' kembali tersedia untuk transaksi baru.',
        ]);
    }

    public function deactivate(int $accountId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $account = $this->findAccount($owner, $accountId);
        $updated = $this->accountManagementService->deactivate($owner, $account);

        return to_route('tenant.accounts.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Akun dinonaktifkan',
            'message' => $updated->name.' tidak akan dipakai untuk transaksi baru sampai diaktifkan kembali.',
        ]);
    }

    private function resolveAccountForEdit(TenantUser $owner): ?array
    {
        $accountId = request()->integer('edit');

        if ($accountId <= 0) {
            return null;
        }

        $account = $this->findAccount($owner, $accountId);

        return [
            'id' => $account->id,
            'name' => $account->name,
            'account_type' => $account->account_type->value,
            'opening_balance' => number_format((float) $account->opening_balance, 2, '.', ''),
            'is_default' => $account->is_default,
            'is_active' => $account->is_active,
        ];
    }

    private function findAccount(TenantUser $owner, int $accountId): Account
    {
        return Account::query()
            ->where('tenant_id', $owner->tenant_id)
            ->findOrFail($accountId);
    }
}
