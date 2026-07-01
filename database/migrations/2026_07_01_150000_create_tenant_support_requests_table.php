<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_support_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->string('subject', 120);
            $table->text('message');
            $table->string('status', 32)->default('new');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'tenant_support_requests_tenant_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_support_requests');
    }
};
