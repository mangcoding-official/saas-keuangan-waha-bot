<?php

namespace Tests\Feature;

use App\Models\PlatformAdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternalUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_users_page_uses_management_view_and_only_shows_verification_actions_for_pending_users(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Tenant Alpha',
            'tenant_type' => 'personal',
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingUserId = DB::table('tenant_users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Budi Pending',
            'role' => 'member',
            'user_status' => 'active',
            'whatsapp_number' => '081234567890',
            'whatsapp_number_normalized' => '6281234567890',
            'verification_status' => 'pending_verification',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activation_codes')->insert([
            'tenant_user_id' => $pendingUserId,
            'code_hash' => Hash::make('123456'),
            'code_last4' => '3456',
            'status' => 'active',
            'active_lock' => 1,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verifiedUserId = DB::table('tenant_users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Sari Verified',
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081111111111',
            'whatsapp_number_normalized' => '6281111111111',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->get(route('internal.users.index'));

        $response->assertOk();
        $response->assertSee('Manajemen User Tenant');
        $response->assertSee('Budi Pending');
        $response->assertSee('Sari Verified');
        $response->assertSee(route('internal.verification.resend', $pendingUserId), false);
        $response->assertSee(route('internal.verification.regenerate', $pendingUserId), false);
        $response->assertDontSee(route('internal.verification.resend', $verifiedUserId), false);
        $response->assertDontSee(route('internal.verification.regenerate', $verifiedUserId), false);
    }
}
