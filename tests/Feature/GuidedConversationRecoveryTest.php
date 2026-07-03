<?php

namespace Tests\Feature;

use App\Enums\ConversationIntentType;
use App\Enums\ConversationSessionStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Attachment;
use App\Models\ConversationSession;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\AttachmentStorageService;
use App\Services\ConversationSessionService;
use App\Services\TransactionMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class GuidedConversationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guided_flow_recovers_active_session_when_active_lock_is_missing(): void
    {
        $tenantUser = $this->createTenantUser();
        $service = app(TransactionMessageService::class);
        $startedAt = now();

        $firstStep = $service->handle($tenantUser, 'keluar', $startedAt, 'guided-start');

        $this->assertSame('guided_expense_amount', $firstStep['route']);

        $session = ConversationSession::query()->where('tenant_user_id', $tenantUser->id)->latest('id')->firstOrFail();

        $session->forceFill([
            'active_lock' => null,
        ])->save();

        $secondStep = $service->handle($tenantUser, '100000', $startedAt->copy()->addSecond(), 'guided-amount');

        $session->refresh();

        $this->assertSame('guided_expense_description', $secondStep['route']);
        $this->assertSame('guided_expense_description', $session->current_state);
        $this->assertSame(1, $session->active_lock);
        $this->assertSame(100000.0, data_get($session->draft_payload, 'items.0.amount'));
    }

    public function test_guided_flow_recovers_recently_expired_session_for_next_input(): void
    {
        $tenantUser = $this->createTenantUser();
        $service = app(TransactionMessageService::class);
        $startedAt = now();

        $firstStep = $service->handle($tenantUser, 'keluar', $startedAt, 'guided-start-expired');

        $this->assertSame('guided_expense_amount', $firstStep['route']);

        $session = ConversationSession::query()->where('tenant_user_id', $tenantUser->id)->latest('id')->firstOrFail();

        $session->forceFill([
            'status' => ConversationSessionStatus::EXPIRED,
            'active_lock' => null,
            'expired_at' => $startedAt->copy()->addSecond(),
        ])->save();

        $secondStep = $service->handle($tenantUser, '100000', $startedAt->copy()->addSeconds(2), 'guided-amount-expired');

        $session->refresh();

        $this->assertSame('guided_expense_description', $secondStep['route']);
        $this->assertSame(ConversationSessionStatus::ACTIVE, $session->status);
        $this->assertSame('guided_expense_description', $session->current_state);
        $this->assertSame(1, $session->active_lock);
        $this->assertNull($session->expired_at);
        $this->assertSame(100000.0, data_get($session->draft_payload, 'items.0.amount'));
    }

    public function test_attachment_flow_recovers_recent_session_for_image_upload(): void
    {
        $tenantUser = $this->createTenantUser();
        $service = app(TransactionMessageService::class);
        $startedAt = now();

        $session = ConversationSession::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'status' => ConversationSessionStatus::EXPIRED,
            'active_lock' => null,
            'current_state' => 'guided_income_attachment_offer',
            'intent_type' => ConversationIntentType::INCOME,
            'draft_payload' => [
                'items' => [[
                    'amount' => 200000,
                    'description' => 'Bonus project',
                    'type' => 'income',
                    'transaction_date' => $startedAt->toDateString(),
                ]],
                'review_ready' => false,
            ],
            'source_message_id' => 'guided-attachment-start',
            'last_message_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes(30),
            'expired_at' => $startedAt->copy()->addSeconds(10),
        ]);

        $attachment = Attachment::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'uploaded_by_user_id' => $tenantUser->id,
            'conversation_session_id' => $session->id,
            'source_message_id' => 'guided-attachment-image',
            'storage_disk' => 'local',
            'storage_path' => 'attachments/test-image.jpg',
            'original_file_name' => 'proof.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 12345,
            'width' => 800,
            'height' => 600,
        ]);

        $this->mock(AttachmentStorageService::class, function (MockInterface $mock) use ($tenantUser, $session, $attachment): void {
            $mock->shouldReceive('storeFromWaha')
                ->once()
                ->withArgs(function (
                    TenantUser $passedUser,
                    ConversationSession $passedSession,
                    string $sourceMessageId,
                    array $media,
                ) use ($tenantUser, $session): bool {
                    return $passedUser->is($tenantUser)
                        && $passedSession->id === $session->id
                        && $sourceMessageId === 'guided-attachment-image'
                        && ($media['url'] ?? null) === 'https://example.com/proof.jpg';
                })
                ->andReturn($attachment);
        });

        $result = $service->handleAttachment(
            $tenantUser,
            [
                'url' => 'https://example.com/proof.jpg',
                'mime_type' => 'image/jpeg',
                'file_name' => 'proof.jpg',
                'file_size' => 12345,
                'width' => 800,
                'height' => 600,
            ],
            $startedAt->copy()->addSeconds(15),
            'guided-attachment-image',
        );

        $session->refresh();

        $this->assertSame('attachment_added', $result['route']);
        $this->assertSame(ConversationSessionStatus::ACTIVE, $session->status);
        $this->assertSame('review_confirm', $session->current_state);
        $this->assertSame(1, $session->active_lock);
        $this->assertNull($session->expired_at);
        $this->assertSame([$attachment->id], data_get($session->draft_payload, 'attachment_ids'));
        $this->assertTrue((bool) data_get($session->draft_payload, 'review_ready'));
    }

    public function test_latest_session_summary_handles_enum_status_without_crashing(): void
    {
        $tenantUser = $this->createTenantUser();

        ConversationSession::query()->create([
            'tenant_id' => $tenantUser->tenant_id,
            'tenant_user_id' => $tenantUser->id,
            'status' => ConversationSessionStatus::ACTIVE,
            'active_lock' => 1,
            'current_state' => 'guided_income_attachment_offer',
            'intent_type' => ConversationIntentType::INCOME,
            'draft_payload' => [],
            'source_message_id' => 'guided-summary-check',
            'last_message_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $summary = app(ConversationSessionService::class)->latestSessionSummary($tenantUser);

        $this->assertNotNull($summary);
        $this->assertSame('active', $summary['status']);
        $this->assertSame('guided_income_attachment_offer', $summary['current_state']);
    }

    private function createTenantUser(): TenantUser
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Guided Recovery',
            'tenant_type' => TenantType::TEAM,
            'timezone' => 'Asia/Jakarta',
            'tenant_status' => 'active',
            'service_plan' => 'alpha',
            'service_status' => 'active',
            'ai_addon_status' => 'inactive',
        ]);

        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ardy Guided',
            'email' => 'ardy-guided@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'user_status' => 'active',
            'whatsapp_number' => '081211112222',
            'whatsapp_number_normalized' => '6281211112222',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
    }
}
