<?php

namespace Tests\Feature;

use App\Enums\InviteStatus;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantRegistrationInviteGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_invite_code(): void
    {
        $response = $this->from(route('tenant.register.create'))->post(route('tenant.register.store'), $this->registrationPayload([
            'invite_code' => '',
        ]));

        $response->assertRedirect(route('tenant.register.create'));
        $response->assertSessionHasErrors('invite_code');
    }

    public function test_register_rejects_invalid_invite_code(): void
    {
        $response = $this->from(route('tenant.register.create'))->post(route('tenant.register.store'), $this->registrationPayload([
            'invite_code' => 'ALPHA-XXXX-YYYY',
        ]));

        $response->assertRedirect(route('tenant.register.create'));
        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_register_marks_invite_as_used_after_successful_registration(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-QWER-ASDF',
            'code_normalized' => 'ALPHAQWERASDF',
            'status' => InviteStatus::PENDING,
            'invited_email' => 'owner@example.com',
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this->post(route('tenant.register.store'), $this->registrationPayload([
            'invite_code' => $invite->code,
            'owner_email' => 'owner@example.com',
        ]));

        $response->assertRedirect(route('tenant.register.success'));

        $invite->refresh();

        $this->assertSame(InviteStatus::USED, $invite->status);
        $this->assertNotNull($invite->used_at);
        $this->assertDatabaseHas('tenant_users', [
            'email' => 'owner@example.com',
            'role' => 'owner',
        ]);
        $this->assertNotNull($invite->used_by_tenant_id);
        $this->assertNotNull($invite->used_by_tenant_user_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'invite_code' => 'ALPHA-ABCD-EFGH',
            'tenant_name' => 'Tenant Alpha',
            'tenant_type' => 'personal',
            'timezone' => 'Asia/Jakarta',
            'owner_name' => 'Owner Baru',
            'owner_whatsapp' => '081234567890',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
        ], $overrides);
    }
}
