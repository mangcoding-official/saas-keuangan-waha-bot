<?php

namespace Tests\Feature;

use App\Models\PlatformAdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternalWahaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_request_waha_reconnect_and_audit_is_written(): void
    {
        config()->set('services.waha.base_url', 'http://localhost:3000');
        config()->set('services.waha.api_key', 'test-key');

        Http::fake([
            'http://localhost:3000/api/sessions/default/restart' => Http::response(['ok' => true], 200),
            'http://localhost:3000/api/sessions/default' => Http::response(['status' => 'STARTING'], 200),
        ]);

        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $botId = \DB::table('bot_instances')->insertGetId([
            'name' => 'Default Bot',
            'waha_instance_key' => 'default',
            'connection_status' => 'disconnected',
            'qr_status' => 'unknown',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->post(route('internal.waha.reconnect', $botId));

        $response->assertRedirect(route('internal.waha.index', ['show' => $botId]));
        $response->assertSessionHas('status.title', 'Reconnect WAHA dikirim');

        $this->assertDatabaseHas('bot_instances', [
            'id' => $botId,
            'connection_status' => 'connecting',
        ]);

        $this->assertDatabaseHas('platform_admin_audit_logs', [
            'platform_admin_user_id' => $admin->id,
            'action' => 'waha_reconnect_requested',
            'target_entity_type' => 'bot_instance',
            'target_entity_id' => $botId,
        ]);
    }
}
