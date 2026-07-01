<?php

namespace Tests\Feature;

use App\Enums\InviteStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantRegistrationInviteGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_keeps_form_locked_without_valid_invite(): void
    {
        $response = $this->get(route('tenant.register.create'));

        $response->assertOk();
        $response->assertSee('Masukkan kode invite aktif untuk membuka form registrasi alpha.');
        $response->assertSee('fieldset disabled', false);
    }

    public function test_register_page_unlocks_form_for_valid_invite_link(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-LINK-TEST',
            'code_normalized' => 'ALPHALINKTEST',
            'status' => InviteStatus::PENDING,
            'invited_email' => 'owner@example.com',
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this->get(route('tenant.register.create', ['invite' => $invite->code]));

        $response->assertOk();
        $response->assertSee('Invite valid terdeteksi. Lengkapi data workspace dan akun owner untuk lanjut registrasi.');
        $response->assertDontSee('fieldset disabled', false);
        $response->assertSee('value="owner@example.com"', false);
    }

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

    public function test_register_reuses_existing_pending_owner_when_email_and_whatsapp_are_already_registered(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Lama',
            'tenant_type' => TenantType::PERSONAL,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        $owner = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner Lama',
            'email' => 'owner@example.com',
            'password' => Hash::make('password-lama'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081234567890',
            'whatsapp_number_normalized' => '6281234567890',
            'verification_status' => VerificationStatus::PENDING_VERIFICATION,
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-ZXCV-BNMK',
            'code_normalized' => 'ALPHAZXCVBNMK',
            'status' => InviteStatus::PENDING,
            'invited_email' => 'owner@example.com',
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this->post(route('tenant.register.store'), $this->registrationPayload([
            'invite_code' => $invite->code,
            'owner_email' => 'owner@example.com',
            'owner_whatsapp' => '081234567890',
            'owner_name' => 'Owner Reinvite',
            'tenant_name' => 'Tenant Reinvite',
        ]));

        $response->assertRedirect(route('tenant.register.success'));

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('tenant_users', 1);
        $this->assertDatabaseHas('tenant_users', [
            'id' => $owner->id,
            'name' => 'Owner Reinvite',
            'email' => 'owner@example.com',
            'whatsapp_number_normalized' => '6281234567890',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Tenant Reinvite',
        ]);

        $invite->refresh();

        $this->assertSame(InviteStatus::USED, $invite->status);
        $this->assertSame($tenant->id, $invite->used_by_tenant_id);
        $this->assertSame($owner->id, $invite->used_by_tenant_user_id);
    }

    public function test_register_rejects_invite_when_target_whatsapp_does_not_match(): void
    {
        $admin = PlatformAdminUser::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'user_status' => 'active',
        ]);

        $invite = OwnerRegistrationInvite::query()->create([
            'code' => 'ALPHA-WHAT-SAPP',
            'code_normalized' => 'ALPHAWHATSAPP',
            'status' => InviteStatus::PENDING,
            'invited_email' => 'owner@example.com',
            'invited_whatsapp_number' => '081298765432',
            'invited_whatsapp_number_normalized' => '6281298765432',
            'created_by_platform_admin_user_id' => $admin->id,
        ]);

        $response = $this->from(route('tenant.register.create', ['invite' => $invite->code]))->post(route('tenant.register.store'), $this->registrationPayload([
            'invite_code' => $invite->code,
            'owner_email' => 'owner@example.com',
            'owner_whatsapp' => '081234567890',
        ]));

        $response->assertRedirect(route('tenant.register.create', ['invite' => $invite->code]));
        $response->assertSessionHasErrors('owner_whatsapp');
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_users', 0);

        $invite->refresh();
        $this->assertSame(InviteStatus::PENDING, $invite->status);
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
