<?php

namespace App\Services;

use App\Enums\TenantType;
use App\Support\CategoryCatalog;
use Illuminate\Support\Facades\DB;

class CategoryTemplateService
{
    public function createDefaultsForTenant(int $tenantId, TenantType $tenantType): void
    {
        $rows = array_map(
            fn (array $template): array => $this->rowForTemplate($tenantId, $template),
            CategoryCatalog::templatesForTenantType($tenantType),
        );

        if ($rows === []) {
            return;
        }

        DB::table('categories')->insert($rows);
    }

    public function ensureDefaultsForTenant(int $tenantId, TenantType $tenantType): void
    {
        $rows = array_map(
            fn (array $template): array => $this->rowForTemplate($tenantId, $template),
            CategoryCatalog::templatesForTenantType($tenantType),
        );

        if ($rows === []) {
            return;
        }

        DB::table('categories')->upsert(
            $rows,
            ['tenant_id', 'type', 'key'],
            ['name', 'keywords', 'is_system', 'is_active', 'updated_at'],
        );
    }

    /**
     * @param  array{key: string, type: string, label: string, is_system: bool, preset_key: string}  $template
     * @return array<string, mixed>
     */
    private function rowForTemplate(int $tenantId, array $template): array
    {
        return [
            'tenant_id' => $tenantId,
            'type' => $template['type'],
            'key' => $template['key'],
            'name' => $template['label'],
            'visual_preset_key' => $template['preset_key'],
            'keywords' => null,
            'is_system' => $template['is_system'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
