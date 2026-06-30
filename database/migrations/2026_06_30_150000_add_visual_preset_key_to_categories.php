<?php

use App\Enums\CategoryType;
use App\Enums\TenantType;
use App\Support\CategoryCatalog;
use App\Support\CategoryVisualCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('visual_preset_key', 120)->nullable()->after('name');
            $table->index(['tenant_id', 'visual_preset_key'], 'categories_tenant_visual_preset_idx');
        });

        $tenants = DB::table('tenants')
            ->select(['id', 'tenant_type'])
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $tenantType = TenantType::from((string) $tenant->tenant_type);
            $defaults = [];

            foreach (CategoryCatalog::templatesForTenantType($tenantType) as $template) {
                $defaults[$template['type']][$template['key']] = $template['preset_key'];
            }

            $fallbackIncomePreset = CategoryVisualCatalog::defaultPresetKeyForCustomCategory($tenantType, CategoryType::INCOME);
            $fallbackExpensePreset = CategoryVisualCatalog::defaultPresetKeyForCustomCategory($tenantType, CategoryType::EXPENSE);

            $categories = DB::table('categories')
                ->where('tenant_id', (int) $tenant->id)
                ->get(['id', 'type', 'key']);

            foreach ($categories as $category) {
                $type = (string) $category->type;
                $key = (string) $category->key;
                $presetKey = $defaults[$type][$key] ?? ($type === CategoryType::INCOME->value ? $fallbackIncomePreset : $fallbackExpensePreset);

                DB::table('categories')
                    ->where('id', (int) $category->id)
                    ->update(['visual_preset_key' => $presetKey]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_tenant_visual_preset_idx');
            $table->dropColumn('visual_preset_key');
        });
    }
};
