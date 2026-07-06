<?php

namespace Tests\Feature;

use App\Enums\PasswordResetTokenStatus;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\TenantPasswordResetToken;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_sends_whatsapp_link_and_stores_hashed_token(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $user = $this->createEligibleTenantUser('6281200001111');
        $this->createActiveBot();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.11',
                'HTTP_USER_AGENT' => 'Codex Password Reset Test',
            ])
            ->post(route('tenant.password.email'), [
                'whatsapp_number' => '081200001111',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status.title', 'Permintaan diterima');
        $response->assertSessionHas('status.message', 'Jika nomor terdaftar, link reset password akan dikirim ke WhatsApp Anda.');

        /** @var TenantPasswordResetToken $resetToken */
        $resetToken = TenantPasswordResetToken::query()->firstOrFail();

        $this->assertSame(PasswordResetTokenStatus::SENT, $resetToken->status);
        $this->assertSame($user->id, $resetToken->tenant_user_id);
        $this->assertNotNull($resetToken->sent_at);
        $this->assertNull($resetToken->used_at);
        $this->assertNotNull($resetToken->lookup_key);
        $this->assertSame('203.0.113.11', $resetToken->requested_ip_address);
        $this->assertSame('Codex Password Reset Test', $resetToken->requested_user_agent);
        $this->assertNotNull($resetToken->requested_at);

        $sentRequest = null;

        Http::assertSent(function ($request) use (&$sentRequest, $user) {
            $sentRequest = $request;

            return $request->url() === 'https://waha.test/api/sendText'
                && $request['session'] === 'bot-primary'
                && $request['chatId'] === $user->whatsapp_number_normalized.'@c.us';
        });

        $this->assertNotNull($sentRequest);

        $resetLink = $this->extractResetLink((string) $sentRequest['text']);

        $this->assertStringContainsString((string) $resetToken->lookup_key, $resetLink);

        parse_str((string) parse_url($resetLink, PHP_URL_QUERY), $query);
        $plainToken = (string) ($query['token'] ?? '');

        $this->assertNotSame('', $plainToken);
        $this->assertNotSame($plainToken, $resetToken->getRawOriginal('token_hash'));
        $this->assertTrue(Hash::check($plainToken, (string) $resetToken->getRawOriginal('token_hash')));
    }

    public function test_only_active_verified_user_on_active_tenant_and_service_can_receive_reset_link(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $this->createActiveBot();

        $cases = [
            [
                'number' => '6281200005551',
                'tenant_status' => TenantStatus::INACTIVE,
                'service_status' => ServiceStatus::ACTIVE,
                'user_status' => UserStatus::ACTIVE,
                'verification_status' => VerificationStatus::VERIFIED,
                'expected_reason' => 'tenant_inactive',
            ],
            [
                'number' => '6281200005552',
                'tenant_status' => TenantStatus::ACTIVE,
                'service_status' => ServiceStatus::SUSPENDED,
                'user_status' => UserStatus::ACTIVE,
                'verification_status' => VerificationStatus::VERIFIED,
                'expected_reason' => 'service_inactive',
            ],
            [
                'number' => '6281200005553',
                'tenant_status' => TenantStatus::ACTIVE,
                'service_status' => ServiceStatus::ACTIVE,
                'user_status' => UserStatus::INACTIVE,
                'verification_status' => VerificationStatus::VERIFIED,
                'expected_reason' => 'user_inactive',
            ],
            [
                'number' => '6281200005554',
                'tenant_status' => TenantStatus::ACTIVE,
                'service_status' => ServiceStatus::ACTIVE,
                'user_status' => UserStatus::ACTIVE,
                'verification_status' => VerificationStatus::PENDING_VERIFICATION,
                'expected_reason' => 'user_not_verified',
            ],
        ];

        foreach ($cases as $index => $case) {
            $user = $this->createEligibleTenantUser(
                normalizedWhatsapp: $case['number'],
                tenantStatus: $case['tenant_status'],
                serviceStatus: $case['service_status'],
                userStatus: $case['user_status'],
                verificationStatus: $case['verification_status'],
                email: 'reset'.$index.'@example.com',
            );

            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.(20 + $index)])
                ->post(route('tenant.password.email'), [
                    'whatsapp_number' => '0'.substr($case['number'], 2),
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('status.message', 'Jika nomor terdaftar, link reset password akan dikirim ke WhatsApp Anda.');

            $resetToken = TenantPasswordResetToken::query()->where('tenant_user_id', $user->id)->latest('id')->firstOrFail();

            $this->assertSame(PasswordResetTokenStatus::REJECTED, $resetToken->status);
            $this->assertSame($case['expected_reason'], $resetToken->failure_reason);
        }

        Http::assertNothingSent();
    }

    public function test_password_reset_request_stays_neutral_for_unknown_number(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $response = $this->post(route('tenant.password.email'), [
            'whatsapp_number' => '081299998888',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status.title', 'Permintaan diterima');
        $response->assertSessionHas('status.message', 'Jika nomor terdaftar, link reset password akan dikirim ke WhatsApp Anda.');

        $resetToken = TenantPasswordResetToken::query()->firstOrFail();

        $this->assertSame(PasswordResetTokenStatus::REJECTED, $resetToken->status);
        $this->assertSame('unknown_user', $resetToken->failure_reason);
        Http::assertNothingSent();
    }

    public function test_password_reset_link_can_only_be_used_once_and_updates_password(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $user = $this->createEligibleTenantUser('6281200002222');
        $this->createActiveBot();

        $this->post(route('tenant.password.email'), [
            'whatsapp_number' => '081200002222',
        ]);

        $sentRequest = null;
        Http::assertSent(function ($request) use (&$sentRequest) {
            $sentRequest = $request;

            return true;
        });

        $resetLink = $this->extractResetLink((string) $sentRequest['text']);
        $lookup = basename((string) parse_url($resetLink, PHP_URL_PATH));
        parse_str((string) parse_url($resetLink, PHP_URL_QUERY), $query);
        $plainToken = (string) ($query['token'] ?? '');

        $this->get($resetLink)
            ->assertOk()
            ->assertSee('Buat password baru');

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.22',
                'HTTP_USER_AGENT' => 'Codex Password Consume Test',
            ])
            ->post(route('tenant.password.reset.update', $lookup), [
                'token' => $plainToken,
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ]);

        $response->assertRedirect(route('tenant.login.create'));
        $response->assertSessionHas('status.title', 'Password berhasil diperbarui');

        $user->refresh();

        $this->assertTrue(Hash::check('password-baru-123', (string) $user->password));

        $resetToken = TenantPasswordResetToken::query()->firstOrFail();
        $this->assertSame(PasswordResetTokenStatus::USED, $resetToken->status);
        $this->assertNotNull($resetToken->used_at);
        $this->assertSame('203.0.113.22', $resetToken->consumed_ip_address);
        $this->assertSame('Codex Password Consume Test', $resetToken->consumed_user_agent);

        $reuseResponse = $this
            ->from($resetLink)
            ->post(route('tenant.password.reset.update', $lookup), [
                'token' => $plainToken,
                'password' => 'password-lain-456',
                'password_confirmation' => 'password-lain-456',
            ]);

        $reuseResponse->assertRedirect($resetLink);
        $reuseResponse->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue(Hash::check('password-baru-123', (string) $user->password));
    }

    public function test_expired_password_reset_link_is_rejected_and_marked_expired(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $this->createEligibleTenantUser('6281200003333');
        $this->createActiveBot();

        $this->post(route('tenant.password.email'), [
            'whatsapp_number' => '081200003333',
        ]);

        $sentRequest = null;
        Http::assertSent(function ($request) use (&$sentRequest) {
            $sentRequest = $request;

            return true;
        });

        $resetLink = $this->extractResetLink((string) $sentRequest['text']);

        $this->travel(config('platform.password_resets.expire_minutes') + 1)->minutes();

        $response = $this->get($resetLink);

        $response->assertOk();
        $response->assertSee('Link reset password tidak valid atau sudah kedaluwarsa.');

        $resetToken = TenantPasswordResetToken::query()->firstOrFail();
        $this->assertSame(PasswordResetTokenStatus::EXPIRED, $resetToken->status);
        $this->assertNotNull($resetToken->expired_at);
    }

    public function test_password_reset_request_is_rate_limited_per_number_and_ip(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $this->createEligibleTenantUser('6281200004444');
        $this->createActiveBot();

        foreach (range(1, 4) as $attempt) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
                ->post(route('tenant.password.email'), [
                    'whatsapp_number' => '081200004444',
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('status.title', 'Permintaan diterima');
        }

        Http::assertSentCount(3);

        $latestToken = TenantPasswordResetToken::query()->latest('id')->firstOrFail();

        $this->assertSame(PasswordResetTokenStatus::RATE_LIMITED, $latestToken->status);
        $this->assertSame('number_rate_limited', $latestToken->failure_reason);
    }

    public function test_password_reset_request_is_rate_limited_per_ip_even_when_numbers_differ(): void
    {
        config()->set('services.waha.base_url', 'https://waha.test');
        config()->set('services.waha.api_key', 'test-key');
        Http::fake(['https://waha.test/*' => Http::response([], 200)]);

        $this->createActiveBot();

        foreach (range(1, 4) as $suffix) {
            $normalizedNumber = '62812000066'.$suffix.$suffix;
            $this->createEligibleTenantUser(
                normalizedWhatsapp: $normalizedNumber,
                email: 'ip-limit-'.$suffix.'@example.com',
            );

            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
                ->post(route('tenant.password.email'), [
                    'whatsapp_number' => '0'.substr($normalizedNumber, 2),
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('status.message', 'Jika nomor terdaftar, link reset password akan dikirim ke WhatsApp Anda.');
        }

        Http::assertSentCount(3);

        $latestToken = TenantPasswordResetToken::query()->latest('id')->firstOrFail();

        $this->assertSame(PasswordResetTokenStatus::RATE_LIMITED, $latestToken->status);
        $this->assertSame('ip_rate_limited', $latestToken->failure_reason);
    }

    private function createEligibleTenantUser(
        string $normalizedWhatsapp,
        TenantStatus $tenantStatus = TenantStatus::ACTIVE,
        ServiceStatus $serviceStatus = ServiceStatus::ACTIVE,
        UserStatus $userStatus = UserStatus::ACTIVE,
        VerificationStatus $verificationStatus = VerificationStatus::VERIFIED,
        ?string $email = null,
    ): TenantUser {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Reset Password',
            'tenant_type' => 'personal',
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => $tenantStatus,
            'service_plan' => 'alpha',
            'service_status' => $serviceStatus,
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reset User',
            'email' => $email ?? 'reset@example.com',
            'password' => 'password-lama',
            'role' => UserRole::OWNER,
            'user_status' => $userStatus,
            'whatsapp_number' => '0812'.substr($normalizedWhatsapp, -8),
            'whatsapp_number_normalized' => $normalizedWhatsapp,
            'verification_status' => $verificationStatus,
            'verified_at' => $verificationStatus === VerificationStatus::VERIFIED ? now() : null,
        ]);
    }

    private function createActiveBot(): void
    {
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
    }

    private function extractResetLink(string $message): string
    {
        preg_match('/https?:\/\/\S+/', $message, $matches);

        $this->assertNotEmpty($matches);

        return rtrim((string) $matches[0]);
    }
}
