<?php

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantSupportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_support_card_points_to_support_route(): void
    {
        $user = $this->createTenantUser();

        $response = $this
            ->actingAs($user, 'web')
            ->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee(route('tenant.support'), false);
    }

    public function test_support_page_shows_whatsapp_channel_when_configured(): void
    {
        config()->set('platform.support.whatsapp_number', '628123456789');

        $user = $this->createTenantUser();

        $response = $this
            ->actingAs($user, 'web')
            ->get(route('tenant.support'));

        $response->assertOk();
        $response->assertSee('Chat WhatsApp CS');
        $response->assertSee('https://wa.me/628123456789', false);
    }

    public function test_support_form_submission_is_persisted(): void
    {
        $user = $this->createTenantUser();

        $response = $this
            ->actingAs($user, 'web')
            ->post(route('tenant.support.store'), [
                'subject' => 'Aktivasi WhatsApp belum masuk',
                'message' => 'Saya sudah mengikuti langkah aktivasi, tetapi balasan bot belum masuk sampai sekarang.',
            ]);

        $response->assertRedirect(route('tenant.support'));
        $this->assertDatabaseHas('tenant_support_requests', [
            'tenant_id' => $user->tenant_id,
            'tenant_user_id' => $user->id,
            'subject' => 'Aktivasi WhatsApp belum masuk',
            'status' => 'new',
        ]);
    }

    private function createTenantUser(): TenantUser
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Support Alpha',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Nadia Support',
            'email' => 'nadia@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081211112222',
            'whatsapp_number_normalized' => '6281211112222',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
    }
}
