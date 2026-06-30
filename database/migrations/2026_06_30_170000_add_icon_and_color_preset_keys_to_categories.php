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
            $table->string('icon_key', 120)->nullable()->after('visual_preset_key');
            $table->string('color_preset_key', 120)->nullable()->after('icon_key');
            $table->index(['tenant_id', 'icon_key'], 'categories_tenant_icon_key_idx');
            $table->index(['tenant_id', 'color_preset_key'], 'categories_tenant_color_preset_key_idx');
        });

        $tenants = DB::table('tenants')
            ->select(['id', 'tenant_type'])
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $tenantType = TenantType::from((string) $tenant->tenant_type);
            $defaults = [];

            foreach (CategoryCatalog::templatesForTenantType($tenantType) as $template) {
                $defaults[$template['type']][$template['key']] = [
                    'icon_key' => $template['icon_key'],
                    'color_preset_key' => $template['color_preset_key'],
                ];
            }

            $fallbackIncomeIcon = CategoryVisualCatalog::defaultIconKeyForCustomCategory($tenantType, CategoryType::INCOME);
            $fallbackExpenseIcon = CategoryVisualCatalog::defaultIconKeyForCustomCategory($tenantType, CategoryType::EXPENSE);
            $fallbackIncomeColor = CategoryVisualCatalog::defaultColorPresetKeyForCustomCategory($tenantType, CategoryType::INCOME);
            $fallbackExpenseColor = CategoryVisualCatalog::defaultColorPresetKeyForCustomCategory($tenantType, CategoryType::EXPENSE);

            $categories = DB::table('categories')
                ->where('tenant_id', (int) $tenant->id)
                ->get(['id', 'type', 'key', 'visual_preset_key']);

            foreach ($categories as $category) {
                $type = (string) $category->type;
                $key = (string) $category->key;
                $legacyPresetKey = is_string($category->visual_preset_key) && $category->visual_preset_key !== ''
                    ? $category->visual_preset_key
                    : null;
                $default = $defaults[$type][$key] ?? null;

                DB::table('categories')
                    ->where('id', (int) $category->id)
                    ->update([
                        'icon_key' => $legacyPresetKey ?? $default['icon_key'] ?? ($type === CategoryType::INCOME->value ? $fallbackIncomeIcon : $fallbackExpenseIcon),
                        'color_preset_key' => $legacyPresetKey ?? $default['color_preset_key'] ?? ($type === CategoryType::INCOME->value ? $fallbackIncomeColor : $fallbackExpenseColor),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_tenant_icon_key_idx');
            $table->dropIndex('categories_tenant_color_preset_key_idx');
            $table->dropColumn(['icon_key', 'color_preset_key']);
        });
    }
};
