<?php

use App\Enums\PasswordResetTokenStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_password_reset_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('lookup_key', 26)->nullable()->unique();
            $table->string('token_hash')->nullable();
            $table->enum('status', PasswordResetTokenStatus::values());
            $table->string('requested_whatsapp_number', 32);
            $table->string('requested_whatsapp_number_normalized', 32);
            $table->string('requested_ip_address', 45)->nullable();
            $table->string('requested_user_agent', 255)->nullable();
            $table->string('delivery_channel', 20)->nullable();
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('consumed_ip_address', 45)->nullable();
            $table->string('consumed_user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_user_id', 'status', 'expires_at'], 'tp_reset_user_status_expires_idx');
            $table->index(['requested_whatsapp_number_normalized', 'requested_at'], 'tp_reset_number_requested_idx');
            $table->index(['status', 'requested_at'], 'tp_reset_status_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_password_reset_tokens');
    }
};
