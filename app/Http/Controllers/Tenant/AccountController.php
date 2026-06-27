<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\TenantUser;
use App\Services\AccountBalanceService;
use App\Services\AccountManagementService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountManagementService $accountManagementService,
        private readonly AccountBalanceService $accountBalanceService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $tenantId = $owner->tenant_id;
        $accountBalances = $this->accountBalanceService->balancesForTenant($tenantId);
        $search = trim((string) $request->query('search', ''));

        $baseQuery = Account::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name');

        $allAccounts = (clone $baseQuery)
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'account_type_value' => $account->account_type->value,
                'account_type' => $this->accountTypeLabel($account->account_type->value),
                'current_balance' => number_format((float) ($accountBalances[(int) $account->id] ?? 0.0), 0, ',', '.'),
                'opening_balance' => number_format((float) $account->opening_balance, 0, ',', '.'),
                'is_default' => $account->is_default,
                'is_active' => $account->is_active,
                'updated_at' => Carbon::parse($account->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $accountPaginator = (clone $baseQuery)
            ->paginate(10)
            ->withQueryString();

        $accounts = $accountPaginator->getCollection()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'account_type_value' => $account->account_type->value,
                'account_type' => $this->accountTypeLabel($account->account_type->value),
                'current_balance' => number_format((float) ($accountBalances[(int) $account->id] ?? 0.0), 0, ',', '.'),
                'opening_balance' => number_format((float) $account->opening_balance, 0, ',', '.'),
                'is_default' => $account->is_default,
                'is_active' => $account->is_active,
                'updated_at' => Carbon::parse($account->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $activeCount = count(array_filter($accounts, fn (array $account): bool => $account['is_active']));
        $inactiveCount = count($allAccounts) - count(array_filter($allAccounts, fn (array $account): bool => $account['is_active']));
        $defaultAccount = collect($allAccounts)->firstWhere('is_default', true);

        return view('tenant.accounts.index', [
            'page' => [
                'title' => '',
                'description' => '',
                'eyebrow' => 'Accounts',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Cari akun...',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'summary' => [
                'total' => count($allAccounts),
                'active' => count(array_filter($allAccounts, fn (array $account): bool => $account['is_active'])),
                'inactive' => $inactiveCount,
                'default_name' => $defaultAccount['name'] ?? '-',
            ],
            'accounts' => $accounts,
            'accountPaginator' => $accountPaginator,
            'activeSearch' => $search,
        ]);
    }

    public function create(): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();

        return view('tenant.accounts.create', $this->accountFormPageData(
            owner: $owner,
            formTitle: 'Create Account',
            submitLabel: 'Simpan Akun',
            formAction: route('tenant.accounts.store'),
            formMethod: 'post',
            account: [
                'name' => '',
                'account_type' => AccountType::BANK->value,
                'opening_balance' => '0.00',
                'is_default' => false,
                'is_active' => true,
            ],
        ));
    }

    public function edit(int $accountId): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $account = $this->findAccount($owner, $accountId);

        return view('tenant.accounts.edit', $this->accountFormPageData(
            owner: $owner,
            formTitle: 'Edit Account',
            submitLabel: 'Simpan Perubahan',
            formAction: route('tenant.accounts.update', $account->id),
            formMethod: 'put',
            account: [
                'id' => $account->id,
                'name' => $account->name,
                'account_type' => $account->account_type->value,
                'opening_balance' => number_format((float) $account->opening_balance, 2, '.', ''),
                'is_default' => $account->is_default,
                'is_active' => $account->is_active,
            ],
        ));
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

        return to_route('tenant.accounts.edit', $updated->id)->with(config('platform.flash_session_key'), [
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

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    private function accountFormPageData(
        TenantUser $owner,
        string $formTitle,
        string $submitLabel,
        string $formAction,
        string $formMethod,
        array $account,
    ): array {
        return [
            'page' => [
                'title' => $formTitle,
                'description' => 'Atur identitas akun, tipe akun, opening balance, dan set default account.',
                'eyebrow' => 'Accounts',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Cari transaksi...',
                'secondary_action' => [
                    'label' => 'Kembali ke Daftar',
                    'href' => route('tenant.accounts.index'),
                    'variant' => 'secondary',
                ],
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'form' => [
                'title' => $formTitle,
                'submit_label' => $submitLabel,
                'action' => $formAction,
                'method' => $formMethod,
            ],
            'account' => $account,
            'accountTypeOptions' => $this->accountTypeOptions(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function accountTypeOptions(): array
    {
        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => $this->accountTypeLabel($value)],
            AccountType::values()
        );
    }

    private function accountTypeLabel(string $value): string
    {
        return match ($value) {
            AccountType::CASH->value => 'Cash',
            AccountType::BANK->value => 'Bank',
            AccountType::E_WALLET->value => 'E-Wallet',
            default => strtoupper(str_replace('_', '-', $value)),
        };
    }

    private function findAccount(TenantUser $owner, int $accountId): Account
    {
        return Account::query()
            ->where('tenant_id', $owner->tenant_id)
            ->findOrFail($accountId);
    }
}
