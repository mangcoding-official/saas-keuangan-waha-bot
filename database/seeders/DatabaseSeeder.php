<?php

namespace Database\Seeders;

use App\Enums\AiAddonStatus;
use App\Enums\ServicePlan;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\WahaConnectionStatus;
use App\Enums\WahaQrStatus;
use App\Models\PlatformAdminUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['name' => 'Demo Tenant Alpha'],
            [
                'tenant_type' => TenantType::COMPANY,
                'timezone' => config('platform.defaults.tenant_timezone'),
                'tenant_status' => TenantStatus::ACTIVE,
                'service_plan' => ServicePlan::ALPHA,
                'service_status' => ServiceStatus::ACTIVE,
                'ai_addon_status' => AiAddonStatus::INACTIVE,
            ],
        );

        $owner = TenantUser::query()->firstOrCreate(
            ['email' => 'owner@demo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Demo Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::OWNER,
                'user_status' => UserStatus::ACTIVE,
                'whatsapp_number' => '081200000001',
                'whatsapp_number_normalized' => '6281200000001',
                'verification_status' => VerificationStatus::VERIFIED,
                'verified_at' => now(),
            ],
        );

        TenantUser::query()->firstOrCreate(
            ['email' => 'member@demo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Demo Member',
                'password' => Hash::make('password'),
                'role' => UserRole::MEMBER,
                'user_status' => UserStatus::ACTIVE,
                'whatsapp_number' => '081200000002',
                'whatsapp_number_normalized' => '6281200000002',
                'verification_status' => VerificationStatus::VERIFIED,
                'verified_at' => now(),
                'invited_by_user_id' => $owner->id,
            ],
        );

        $platformAdmin = PlatformAdminUser::query()->firstOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name' => 'Demo Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'user_status' => UserStatus::ACTIVE,
            ],
        );

        $botId = DB::table('bot_instances')->where('waha_instance_key', 'default')->value('id');

        if (! $botId) {
            $botId = DB::table('bot_instances')->insertGetId([
                'name' => 'Default Bot',
                'waha_instance_key' => 'default',
                'bot_whatsapp_number' => '081100000000',
                'bot_whatsapp_number_normalized' => '6281100000000',
                'connection_status' => WahaConnectionStatus::CONNECTING->value,
                'qr_status' => WahaQrStatus::UNKNOWN->value,
                'webhook_status' => 'unknown',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('tenant_bot_assignments')->where('tenant_id', $tenant->id)->where('active_lock', 1)->exists()) {
            DB::table('tenant_bot_assignments')->insert([
                'tenant_id' => $tenant->id,
                'bot_instance_id' => $botId,
                'active_lock' => 1,
                'assigned_by_admin_id' => $platformAdmin->id,
                'assigned_at' => now(),
                'note' => 'Seeded default assignment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
