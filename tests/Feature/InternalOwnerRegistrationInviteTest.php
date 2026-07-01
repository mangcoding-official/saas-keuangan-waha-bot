<?php

namespace Tests\Feature;

use App\Enums\InviteStatus;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternalOwnerRegistrationInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_owner_registration_invite(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);
        \DB::table('bot_instances')->insert([
            'name' => 'Primary Bot',
            'waha_instance_key' => 'bot-primary',
            'bot_whatsapp_number' => '081234567890',
            'bot_whatsapp_number_normalized' => '6281234567890',
            'connection_status' => 'connected',
            'qr_status' => 'not_required',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->post(route('internal.invites.store'), [
                'invited_email' => 'alpha@example.com',
                'invited_whatsapp_number' => '081298765432',
                'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'note' => 'Batch alpha Juli',
            ]);

        $response->assertRedirect(route('internal.invites.index'));
        $response->assertSessionHas('status.title', 'Invite berhasil dibuat');

        $invite = OwnerRegistrationInvite::query()->first();

        $this->assertNotNull($invite);
        $this->assertSame(InviteStatus::PENDING, $invite->status);
        $this->assertSame('alpha@example.com', $invite->invited_email);
        $this->assertSame('081298765432', $invite->invited_whatsapp_number);
        $this->assertSame('6281298765432', $invite->invited_whatsapp_number_normalized);
        $this->assertSame('Batch alpha Juli', $invite->note);
        $this->assertStringStartsWith('ALPHA-', $invite->code);

        $this->assertDatabaseHas('platform_admin_audit_logs', [
            'platform_admin_user_id' => $admin->id,
            'action' => 'owner_registration_invite_created',
            'target_entity_type' => 'owner_registration_invite',
            'target_entity_id' => $invite->id,
        ]);

        Http::assertSent(function ($request) use ($invite) {
            return $request->url() === 'https://waha.test/api/sendText'
                && $request['session'] === 'bot-primary'
                && $request['chatId'] === '6281298765432@c.us'
                && str_contains((string) $request['text'], $invite->code);
        });
    }

    public function test_super_admin_can_revoke_pending_invite(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-ABCD-EFGH',
            'code_normalized' => 'ALPHAABCDEFGH',
            'status' => InviteStatus::PENDING,
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->post(route('internal.invites.revoke', $invite->id), [
                'reason' => 'Target batal onboarding',
            ]);

        $response->assertRedirect(route('internal.invites.index'));
        $response->assertSessionHas('status.title', 'Invite dicabut');

        $invite->refresh();

        $this->assertSame(InviteStatus::REVOKED, $invite->status);
        $this->assertNotNull($invite->revoked_at);
        $this->assertSame($admin->id, $invite->revoked_by_platform_admin_user_id);

        $this->assertDatabaseHas('platform_admin_audit_logs', [
            'platform_admin_user_id' => $admin->id,
            'action' => 'owner_registration_invite_revoked',
            'target_entity_type' => 'owner_registration_invite',
            'target_entity_id' => $invite->id,
        ]);
    }

    public function test_super_admin_can_resend_pending_invite_via_whatsapp(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        \DB::table('bot_instances')->insert([
            'name' => 'Primary Bot',
            'waha_instance_key' => 'bot-primary',
            'bot_whatsapp_number' => '081234567890',
            'bot_whatsapp_number_normalized' => '6281234567890',
            'connection_status' => 'connected',
            'qr_status' => 'not_required',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-ABCD-EFGH',
            'code_normalized' => 'ALPHAABCDEFGH',
            'status' => InviteStatus::PENDING,
            'invited_whatsapp_number' => '081298765432',
            'invited_whatsapp_number_normalized' => '6281298765432',
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->post(route('internal.invites.send-whatsapp', $invite->id));

        $response->assertRedirect(route('internal.invites.index'));
        $response->assertSessionHas('status.title', 'Invite berhasil dikirim');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://waha.test/api/sendText'
                && $request['session'] === 'bot-primary'
                && $request['chatId'] === '6281298765432@c.us';
        });
    }
}
