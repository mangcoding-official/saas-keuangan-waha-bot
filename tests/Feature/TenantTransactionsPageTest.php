<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\TenantType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VerificationStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantTransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_transactions_table_shows_all_transactions_by_default_ordered_from_latest_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 20, 0, 0, 'UTC'));

        $owner = $this->createTenantUser();
        [$account, $category] = $this->createExpenseCatalog($owner->tenant_id);

        $latestTransaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Biaya operasional awal bulan',
            '2026-07-01',
        );

        $olderTransaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Biaya operasional akhir bulan lalu',
            '2026-06-30',
        );

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('tenant.transactions.index'));

        $response->assertOk();
        $response->assertSee($latestTransaction->description);
        $response->assertSee($olderTransaction->description);
        $response->assertSeeInOrder([
            $latestTransaction->description,
            $olderTransaction->description,
        ]);
    }

    public function test_transactions_period_filter_still_limits_table_when_query_is_provided(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 20, 0, 0, 'UTC'));

        $owner = $this->createTenantUser();
        [$account, $category] = $this->createExpenseCatalog($owner->tenant_id);

        $includedTransaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Biaya operasional awal bulan',
            '2026-07-01',
        );

        $excludedTransaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Biaya operasional akhir bulan lalu',
            '2026-06-30',
        );

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('tenant.transactions.index', ['period' => 'this_month']));

        $response->assertOk();
        $response->assertSee($includedTransaction->description);
        $response->assertDontSee($excludedTransaction->description);
    }

    public function test_overview_trend_clamps_negative_drop_to_zero_percent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 9, 0, 0, 'Asia/Jakarta'));

        $owner = $this->createTenantUser();
        [$account, $category] = $this->createExpenseCatalog($owner->tenant_id);

        $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Biaya operasional bulan lalu',
            '2026-06-20',
            300000,
        );

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('tenant.transactions.index'));

        $response->assertOk();
        $response->assertSee('0%');
        $response->assertDontSee('-100,0%');
    }

    public function test_export_query_downloads_transactions_csv(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 9, 0, 0, 'Asia/Jakarta'));

        $owner = $this->createTenantUser();
        [$account, $category] = $this->createExpenseCatalog($owner->tenant_id);

        $transaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Langganan software tim',
            '2026-07-01',
            250000,
        );

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('tenant.transactions.index', ['export' => 1]));

        $response->assertOk();
        $response->assertDownload('transactions-2026-07-01.csv');
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Langganan software tim', $response->streamedContent());
        $this->assertStringContainsString((string) $transaction->id, $response->streamedContent());
    }

    public function test_owner_can_open_void_modal_and_void_transaction_from_transactions_page(): void
    {
        $owner = $this->createTenantUser();
        [$account, $category] = $this->createExpenseCatalog($owner->tenant_id);
        $transaction = $this->createExpenseTransaction(
            $owner,
            $account,
            $category,
            'Kas kecil kantor',
            '2026-07-01',
        );

        $pageResponse = $this
            ->actingAs($owner, 'web')
            ->get(route('tenant.transactions.index', ['show' => $transaction->id, 'void' => $transaction->id]));

        $pageResponse->assertOk();
        $pageResponse->assertSee('Alasan void');
        $pageResponse->assertSee(route('tenant.transactions.void', $transaction->id), false);

        $submitResponse = $this
            ->actingAs($owner, 'web')
            ->post(route('tenant.transactions.void', $transaction->id), [
                'void_reason' => 'Transaksi duplikat',
            ]);

        $submitResponse->assertRedirect(route('tenant.transactions.index'));
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::VOID->value,
            'void_reason' => 'Transaksi duplikat',
        ]);
    }

    private function createTenantUser(): TenantUser
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Transactions Alpha',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tari Owner',
            'email' => 'tari.owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081233334444',
            'whatsapp_number_normalized' => '6281233334444',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
    }

    /**
     * @return array{Account, Category}
     */
    private function createExpenseCatalog(int $tenantId): array
    {
        $account = Account::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Kas Operasional',
            'account_type' => AccountType::CASH,
            'is_default' => true,
            'opening_balance' => 1000000,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenantId,
            'type' => CategoryType::EXPENSE,
            'key' => 'biaya-operasional',
            'name' => 'Biaya Operasional',
            'visual_preset_key' => 'expense-default',
            'icon_key' => 'receipt',
            'color_preset_key' => 'expense-default',
            'keywords' => ['operasional'],
            'is_system' => false,
            'is_active' => true,
        ]);

        return [$account, $category];
    }

    private function createExpenseTransaction(
        TenantUser $owner,
        Account $account,
        Category $category,
        string $description,
        string $transactionDate,
        float $amount = 150000,
    ): Transaction {
        return Transaction::query()->create([
            'tenant_id' => $owner->tenant_id,
            'recorded_by_user_id' => $owner->id,
            'type' => TransactionType::EXPENSE,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $transactionDate,
            'status' => TransactionStatus::COMPLETED,
            'category_id' => $category->id,
            'source_account_id' => $account->id,
        ]);
    }
}
