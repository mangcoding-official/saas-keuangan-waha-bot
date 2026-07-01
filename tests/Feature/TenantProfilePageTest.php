<?php

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_profile_page_renders_real_tenant_user_data(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tim Finance Alpha',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        $owner = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rina Owner',
            'email' => 'rina@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081200000001',
            'whatsapp_number_normalized' => '6281200000001',
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => Carbon::parse('2026-06-28 08:00:00'),
        ]);

        $member = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Budi Member',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'user_status' => 'active',
            'whatsapp_number' => '081299999999',
            'whatsapp_number_normalized' => '6281299999999',
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => Carbon::parse('2026-06-29 09:30:00'),
            'invited_by_user_id' => $owner->id,
            'last_login_at' => Carbon::parse('2026-07-01 07:00:00'),
        ]);

        $response = $this
            ->actingAs($member, 'web')
            ->get(route('tenant.profile.show'));

        $response->assertOk();
        $response->assertSee('Profil Saya');
        $response->assertSee('Budi Member');
        $response->assertSee('budi@example.com');
        $response->assertSee('0812****9999');
        $response->assertDontSee('081299999999');
        $response->assertSee('Tim Finance Alpha');
        $response->assertSee('Rina Owner');
        $response->assertSee('TEAM');
        $response->assertSee('WhatsApp terverifikasi');
        $response->assertSee('2');
        $response->assertSee('anggota workspace');
    }
}
