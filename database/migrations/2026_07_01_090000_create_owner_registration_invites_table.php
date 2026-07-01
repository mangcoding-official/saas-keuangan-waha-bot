<?php

use App\Enums\InviteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_registration_invites', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('code_normalized', 32)->unique();
            $table->enum('status', InviteStatus::values())->default(InviteStatus::PENDING->value);
            $table->string('invited_email', 190)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_platform_admin_user_id')->constrained('platform_admin_users')->cascadeOnDelete();
            $table->foreignId('revoked_by_platform_admin_user_id')->nullable()->constrained('platform_admin_users')->nullOnDelete();
            $table->foreignId('used_by_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('used_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['invited_email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_registration_invites');
    }
};
