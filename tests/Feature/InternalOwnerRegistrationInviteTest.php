<?php

namespace Tests\Feature;

use App\Enums\InviteStatus;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternalOwnerRegistrationInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_owner_registration_invite(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $response = $this
            ->actingAs($admin, 'platform_admin')
            ->post(route('internal.invites.store'), [
                'invited_email' => 'alpha@example.com',
                'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'note' => 'Batch alpha Juli',
            ]);

        $response->assertRedirect(route('internal.invites.index'));
        $response->assertSessionHas('status.title', 'Invite berhasil dibuat');

        $invite = OwnerRegistrationInvite::query()->first();

        $this->assertNotNull($invite);
        $this->assertSame(InviteStatus::PENDING, $invite->status);
        $this->assertSame('alpha@example.com', $invite->invited_email);
        $this->assertSame('Batch alpha Juli', $invite->note);
        $this->assertStringStartsWith('ALPHA-', $invite->code);

        $this->assertDatabaseHas('platform_admin_audit_logs', [
            'platform_admin_user_id' => $admin->id,
            'action' => 'owner_registration_invite_created',
            'target_entity_type' => 'owner_registration_invite',
            'target_entity_id' => $invite->id,
        ]);
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
}
