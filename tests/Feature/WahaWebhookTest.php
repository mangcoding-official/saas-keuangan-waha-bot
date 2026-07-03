<?php

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TransactionMessageService;
use App\Services\Waha\WahaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class WahaWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_processes_message_once_and_sends_reply(): void
    {
        DB::table('bot_instances')->insert([
            'name' => 'Default Bot',
            'waha_instance_key' => 'default',
            'connection_status' => 'connected',
            'qr_status' => 'not_required',
            'webhook_status' => 'healthy',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantUser = $this->createVerifiedTenantUser('6281211112222');

        $transactionService = Mockery::mock(TransactionMessageService::class);
        $transactionService
            ->shouldReceive('handle')
            ->once()
            ->withArgs(function (TenantUser $user, string $messageText, $timestamp, ?string $sourceMessageId) use ($tenantUser): bool {
                return $user->is($tenantUser)
                    && $messageText === 'saldo'
                    && $sourceMessageId === 'msg-1';
            })
            ->andReturn([
                'route' => 'command_balance',
                'should_reply' => true,
                'reply_text' => 'Saldo total: Rp 10.000',
                'side_effects' => ['command_balance'],
            ]);
        $transactionService->shouldReceive('handleAttachment')->never();
        $this->instance(TransactionMessageService::class, $transactionService);

        $wahaClient = Mockery::mock(WahaClient::class);
        $wahaClient
            ->shouldReceive('sendText')
            ->once()
            ->with('default', '6281211112222@c.us', 'Saldo total: Rp 10.000');
        $this->instance(WahaClient::class, $wahaClient);

        $response = $this->postJson(route('webhooks.waha'), $this->messagePayload('msg-1', '6281211112222@c.us', 'saldo'));

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'route' => 'command_balance',
                'should_reply' => true,
                'reply_text' => 'Saldo total: Rp 10.000',
            ]);

        $this->assertDatabaseHas('incoming_messages', [
            'source_message_id' => 'msg-1',
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'access_decision' => 'accepted',
            'message_text' => 'saldo',
        ]);
        $this->assertNotNull(DB::table('incoming_messages')->where('source_message_id', 'msg-1')->value('processed_at'));
    }

    public function test_webhook_ignores_duplicate_message_while_same_source_message_is_locked(): void
    {
        $transactionService = Mockery::mock(TransactionMessageService::class);
        $transactionService->shouldReceive('handle')->never();
        $transactionService->shouldReceive('handleAttachment')->never();
        $this->instance(TransactionMessageService::class, $transactionService);

        $wahaClient = Mockery::mock(WahaClient::class);
        $wahaClient->shouldReceive('sendText')->never();
        $this->instance(WahaClient::class, $wahaClient);

        $lock = Cache::lock('waha:webhook:message:msg-locked', 30);
        $this->assertTrue($lock->get());

        try {
            $response = $this->postJson(route('webhooks.waha'), $this->messagePayload('msg-locked', '6281211112222@c.us', 'saldo'));

            $response
                ->assertOk()
                ->assertJson([
                    'status' => 'ok',
                    'route' => 'duplicate_ignored',
                    'should_reply' => false,
                    'reply_text' => null,
                    'side_effects' => ['duplicate_ignored'],
                ]);

            $this->assertDatabaseCount('incoming_messages', 0);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $messageId, string $chatId, string $body): array
    {
        return [
            'event' => 'message',
            'session' => 'default',
            'payload' => [
                'id' => $messageId,
                'from' => $chatId,
                'fromMe' => false,
                'body' => $body,
                'timestamp' => 1719792000,
                'hasMedia' => false,
            ],
        ];
    }

    private function createVerifiedTenantUser(string $whatsappNumberNormalized): TenantUser
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Webhook Alpha',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rina Webhook',
            'email' => 'rina-webhook@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081211112222',
            'whatsapp_number_normalized' => $whatsappNumberNormalized,
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
    }
}
