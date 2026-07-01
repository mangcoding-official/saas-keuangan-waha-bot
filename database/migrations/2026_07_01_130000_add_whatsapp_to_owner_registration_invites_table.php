<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_registration_invites', function (Blueprint $table): void {
            $table->string('invited_whatsapp_number', 32)->nullable()->after('invited_email');
            $table->string('invited_whatsapp_number_normalized', 32)->nullable()->after('invited_whatsapp_number');
            $table->index('invited_whatsapp_number_normalized', 'ori_invited_wa_norm_idx');
        });
    }

    public function down(): void
    {
        Schema::table('owner_registration_invites', function (Blueprint $table): void {
            $table->dropIndex('ori_invited_wa_norm_idx');
            $table->dropColumn([
                'invited_whatsapp_number',
                'invited_whatsapp_number_normalized',
            ]);
        });
    }
};
