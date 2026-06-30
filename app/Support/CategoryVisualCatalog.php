<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\TenantType;

class CategoryVisualCatalog
{
    /**
     * @return array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>
     */
    public static function allIcons(): array
    {
        /** @var array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}> $icons */
        $icons = config('category_visuals.icons', []);

        return $icons;
    }

    /**
     * @return array<int, array{key: string, label: string, bg_color: string, icon_color: string}>
     */
    public static function allColorPresets(): array
    {
        /** @var array<int, array{key: string, label: string, bg_color: string, icon_color: string}> $colors */
        $colors = config('category_visuals.colors', []);

        return $colors;
    }

    /**
     * @return array<int, string>
     */
    public static function iconKeys(): array
    {
        return array_column(self::allIcons(), 'key');
    }

    /**
     * @return array<int, string>
     */
    public static function colorPresetKeys(): array
    {
        return array_column(self::allColorPresets(), 'key');
    }

    /**
     * @return array<int, string>
     */
    public static function allowedIconKeysForTenantType(TenantType $tenantType): array
    {
        return array_column(self::iconsForTenantType($tenantType), 'key');
    }

    public static function hasIconKey(?string $key): bool
    {
        return is_string($key) && in_array($key, self::iconKeys(), true);
    }

    public static function hasColorPresetKey(?string $key): bool
    {
        return is_string($key) && in_array($key, self::colorPresetKeys(), true);
    }

    /**
     * @return array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}|null
     */
    public static function findIcon(?string $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (self::allIcons() as $icon) {
            if ($icon['key'] === $key) {
                return $icon;
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, bg_color: string, icon_color: string}|null
     */
    public static function findColorPreset(?string $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (self::allColorPresets() as $colorPreset) {
            if ($colorPreset['key'] === $key) {
                return $colorPreset;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{group_key: string, group_label: string, is_recommended: bool, icons: array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>}>
     */
    public static function groupedIconsForTenantType(TenantType $tenantType): array
    {
        $groupKey = self::groupForTenantType($tenantType);
        $icons = self::iconsForTenantType($tenantType);

        if ($icons === []) {
            return [];
        }

        return [[
            'group_key' => $groupKey,
            'group_label' => $icons[0]['tenant_group_label'],
            'is_recommended' => true,
            'icons' => $icons,
        ]];
    }

    public static function groupForTenantType(TenantType $tenantType): string
    {
        return match ($tenantType) {
            TenantType::PERSONAL, TenantType::FAMILY => 'personal_family',
            TenantType::UMKM => 'umkm',
            TenantType::TEAM, TenantType::COMPANY => 'team_company',
        };
    }

    public static function defaultIconKeyForCustomCategory(TenantType $tenantType, CategoryType $categoryType): string
    {
        return match (self::groupForTenantType($tenantType)) {
            'personal_family' => $categoryType === CategoryType::INCOME
                ? 'pf_pemasukan_lainnya'
                : 'pf_pengeluaran_lainnya',
            'umkm' => $categoryType === CategoryType::INCOME
                ? 'umkm_pendapatan_lainnya'
                : 'umkm_pengeluaran_lainnya',
            default => $categoryType === CategoryType::INCOME
                ? 'tc_pemasukan_lainnya'
                : 'tc_operasional_kantor_lainnya',
        };
    }

    public static function defaultColorPresetKeyForCustomCategory(TenantType $tenantType, CategoryType $categoryType): string
    {
        return self::defaultIconKeyForCustomCategory($tenantType, $categoryType);
    }

    /**
     * @return array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>
     */
    private static function iconsForTenantType(TenantType $tenantType): array
    {
        $group = self::groupForTenantType($tenantType);

        return array_values(array_filter(
            self::allIcons(),
            fn (array $icon): bool => $icon['tenant_group'] === $group,
        ));
    }
}
