<?php

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\ConversationSession;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TransactionMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuidedConversationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guided_flow_recovers_active_session_when_active_lock_is_missing(): void
    {
        $tenantUser = $this->createTenantUser();
        $service = app(TransactionMessageService::class);
        $startedAt = now();

        $firstStep = $service->handle($tenantUser, 'keluar', $startedAt, 'guided-start');

        $this->assertSame('guided_expense_amount', $firstStep['route']);

        $session = ConversationSession::query()->where('tenant_user_id', $tenantUser->id)->latest('id')->firstOrFail();

        $session->forceFill([
            'active_lock' => null,
        ])->save();

        $secondStep = $service->handle($tenantUser, '100000', $startedAt->copy()->addSecond(), 'guided-amount');

        $session->refresh();

        $this->assertSame('guided_expense_description', $secondStep['route']);
        $this->assertSame('guided_expense_description', $session->current_state);
        $this->assertSame(1, $session->active_lock);
        $this->assertSame(100000.0, data_get($session->draft_payload, 'items.0.amount'));
    }

    public function test_guided_flow_recovers_recently_expired_session_for_next_input(): void
    {
        $tenantUser = $this->createTenantUser();
        $service = app(TransactionMessageService::class);
        $startedAt = now();

        $firstStep = $service->handle($tenantUser, 'keluar', $startedAt, 'guided-start-expired');

        $this->assertSame('guided_expense_amount', $firstStep['route']);

        $session = ConversationSession::query()->where('tenant_user_id', $tenantUser->id)->latest('id')->firstOrFail();

        $session->forceFill([
            'status' => \App\Enums\ConversationSessionStatus::EXPIRED,
            'active_lock' => null,
            'expired_at' => $startedAt->copy()->addSecond(),
        ])->save();

        $secondStep = $service->handle($tenantUser, '100000', $startedAt->copy()->addSeconds(2), 'guided-amount-expired');

        $session->refresh();

        $this->assertSame('guided_expense_description', $secondStep['route']);
        $this->assertSame(\App\Enums\ConversationSessionStatus::ACTIVE, $session->status);
        $this->assertSame('guided_expense_description', $session->current_state);
        $this->assertSame(1, $session->active_lock);
        $this->assertNull($session->expired_at);
        $this->assertSame(100000.0, data_get($session->draft_payload, 'items.0.amount'));
    }

    private function createTenantUser(): TenantUser
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Guided Recovery',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ardy Guided',
            'email' => 'ardy-guided@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081211112222',
            'whatsapp_number_normalized' => '6281211112222',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
    }
}
