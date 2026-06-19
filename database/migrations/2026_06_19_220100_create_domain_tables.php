<?php

use App\Enums\AccountType;
use App\Enums\ActivationCodeStatus;
use App\Enums\AiAddonStatus;
use App\Enums\AuditActorSource;
use App\Enums\CategoryType;
use App\Enums\ConversationIntentType;
use App\Enums\ConversationSessionStatus;
use App\Enums\IncomingMessageAccessDecision;
use App\Enums\IncomingMessageIgnoredReason;
use App\Enums\MessageChatType;
use App\Enums\ServicePlan;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\WahaConnectionStatus;
use App\Enums\WahaQrStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->enum('tenant_type', TenantType::values());
            $table->string('timezone', 64)->default(config('platform.defaults.tenant_timezone'));
            $table->enum('tenant_status', TenantStatus::values())->default(TenantStatus::ACTIVE->value);
            $table->enum('service_plan', ServicePlan::values())->default(config('platform.defaults.service_plan'));
            $table->enum('service_status', ServiceStatus::values())->default(ServiceStatus::ACTIVE->value);
            $table->enum('ai_addon_status', AiAddonStatus::values())->default(AiAddonStatus::INACTIVE->value);
            $table->timestamps();

            $table->index(['tenant_status', 'service_status']);
            $table->index('tenant_type');
            $table->index('ai_addon_status');
        });

        Schema::create('tenant_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 190)->nullable()->unique();
            $table->string('password')->nullable();
            $table->enum('role', UserRole::values());
            $table->enum('user_status', UserStatus::values())->default(UserStatus::ACTIVE->value);
            $table->string('whatsapp_number', 32);
            $table->string('whatsapp_number_normalized', 32)->unique();
            $table->enum('verification_status', VerificationStatus::values())->default(VerificationStatus::PENDING_VERIFICATION->value);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['tenant_id', 'role']);
            $table->index(['tenant_id', 'user_status', 'verification_status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('platform_admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('role', 50)->default('super_admin');
            $table->enum('user_status', UserStatus::values())->default(UserStatus::ACTIVE->value);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'user_status']);
        });

        Schema::create('activation_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->char('code_last4', 4)->nullable();
            $table->enum('status', ActivationCodeStatus::values())->default(ActivationCodeStatus::ACTIVE->value);
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidated_reason', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_user_id', 'active_lock']);
            $table->index(['status', 'expires_at']);
            $table->index(['tenant_user_id', 'created_at']);
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('account_type', AccountType::values());
            $table->boolean('is_default')->default(false);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active', 'is_default']);
            $table->index(['tenant_id', 'account_type']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', CategoryType::values());
            $table->string('name', 120);
            $table->json('keywords')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'name']);
            $table->index(['tenant_id', 'type', 'is_active']);
            $table->index(['tenant_id', 'is_system']);
        });

        Schema::create('bot_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('waha_instance_key', 120)->unique();
            $table->string('bot_whatsapp_number', 32)->nullable();
            $table->string('bot_whatsapp_number_normalized', 32)->nullable()->unique();
            $table->enum('connection_status', WahaConnectionStatus::values());
            $table->enum('qr_status', WahaQrStatus::values());
            $table->string('webhook_status', 50)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_reconnect_at')->nullable();
            $table->timestamp('last_qr_refresh_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['is_default', 'is_active']);
            $table->index(['connection_status', 'qr_status']);
        });

        Schema::create('tenant_bot_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->foreignId('assigned_by_admin_id')->nullable()->constrained('platform_admin_users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'active_lock']);
            $table->index(['bot_instance_id', 'active_lock']);
        });

        Schema::create('incoming_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_message_id', 190)->unique();
            $table->string('sender_masked', 64)->nullable();
            $table->char('sender_hash', 64)->nullable();
            $table->string('sender_normalized', 32)->nullable();
            $table->enum('chat_type', MessageChatType::values());
            $table->boolean('is_from_me')->default(false);
            $table->enum('access_decision', IncomingMessageAccessDecision::values());
            $table->enum('ignored_reason', IncomingMessageIgnoredReason::values())->nullable();
            $table->text('message_text')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tenant_user_id', 'received_at']);
            $table->index(['access_decision', 'ignored_reason', 'received_at'], 'incoming_messages_decision_reason_received_idx');
            $table->index(['chat_type', 'is_from_me']);
        });

        Schema::create('conversation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ConversationSessionStatus::values());
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->string('current_state', 120);
            $table->enum('intent_type', ConversationIntentType::values())->nullable();
            $table->json('draft_payload')->nullable();
            $table->string('source_message_id', 190)->nullable();
            $table->timestamp('last_message_at');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_user_id', 'active_lock']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['tenant_user_id', 'last_message_at']);
            $table->index(['current_state', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('conversation_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_message_id', 190);
            $table->string('storage_disk', 50);
            $table->string('storage_path', 255);
            $table->string('original_file_name', 255)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'uploaded_by_user_id', 'created_at']);
            $table->index('source_message_id');
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('conversation_session_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', TransactionType::values());
            $table->decimal('amount', 18, 2);
            $table->string('description', 255)->nullable();
            $table->date('transaction_date');
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::COMPLETED->value);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('source_message_id', 190)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'transaction_date', 'status']);
            $table->index(['tenant_id', 'type', 'transaction_date']);
            $table->index(['recorded_by_user_id', 'transaction_date']);
            $table->index(['source_account_id', 'transaction_date']);
            $table->index(['destination_account_id', 'transaction_date']);
            $table->index('source_message_id');
        });

        Schema::create('attachment_transaction', function (Blueprint $table): void {
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->primary(['attachment_id', 'transaction_id']);
            $table->index('transaction_id');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('actor_source', AuditActorSource::values());
            $table->foreignId('actor_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('actor_platform_admin_user_id')->nullable()->constrained('platform_admin_users')->nullOnDelete();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 80);
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'action', 'created_at']);
            $table->index(['actor_source', 'created_at']);
        });

        Schema::create('platform_admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 80);
            $table->string('target_entity_type', 80);
            $table->unsignedBigInteger('target_entity_id');
            $table->text('reason_note')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('created_at');

            $table->index(['target_entity_type', 'target_entity_id'], 'platform_admin_audit_target_entity_idx');
            $table->index(['action', 'created_at']);
            $table->index(['platform_admin_user_id', 'created_at'], 'platform_admin_audit_actor_created_idx');
        });

        Schema::create('platform_support_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_user_id')->constrained()->cascadeOnDelete();
            $table->string('target_entity_type', 80);
            $table->unsignedBigInteger('target_entity_id');
            $table->text('note');
            $table->boolean('is_review_marker')->default(false);
            $table->timestamps();

            $table->index(['target_entity_type', 'target_entity_id', 'created_at'], 'platform_support_notes_target_created_idx');
            $table->index(['is_review_marker', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_notes');
        Schema::dropIfExists('platform_admin_audit_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachment_transaction');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('conversation_sessions');
        Schema::dropIfExists('incoming_messages');
        Schema::dropIfExists('tenant_bot_assignments');
        Schema::dropIfExists('bot_instances');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('activation_codes');
        Schema::dropIfExists('platform_admin_users');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }
};
