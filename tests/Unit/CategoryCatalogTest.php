<?php

namespace Tests\Unit;

use App\Enums\CategoryType;
use App\Enums\TenantType;
use App\Support\CategoryCatalog;
use App\Support\CategoryVisualCatalog;
use Tests\TestCase;

class CategoryCatalogTest extends TestCase
{
    public function test_personal_and_family_share_the_same_pack(): void
    {
        $personalTemplates = CategoryCatalog::templatesForTenantType(TenantType::PERSONAL);
        $familyTemplates = CategoryCatalog::templatesForTenantType(TenantType::FAMILY);

        $this->assertSame($personalTemplates, $familyTemplates);
        $this->assertSame('personal_family', CategoryCatalog::packForTenantType(TenantType::PERSONAL));
        $this->assertSame('personal_family', CategoryCatalog::packForTenantType(TenantType::FAMILY));
    }

    public function test_company_uses_team_fallback_categories_for_alpha(): void
    {
        $this->assertSame('team', CategoryCatalog::packForTenantType(TenantType::COMPANY));
        $this->assertSame('other_income', CategoryCatalog::fallbackKeyFor(TenantType::COMPANY, CategoryType::INCOME));
        $this->assertSame('other_office_operations', CategoryCatalog::fallbackKeyFor(TenantType::COMPANY, CategoryType::EXPENSE));
    }

    public function test_umkm_uses_business_specific_fallback_labels(): void
    {
        $templates = CategoryCatalog::templatesForTenantType(TenantType::UMKM);

        $this->assertContains([
            'key' => 'other_income',
            'type' => CategoryType::INCOME->value,
            'label' => 'Pendapatan Lainnya',
            'is_system' => true,
            'icon_key' => 'umkm_pendapatan_lainnya',
            'color_preset_key' => 'umkm_pendapatan_lainnya',
        ], $templates);
        $this->assertContains([
            'key' => 'other_expense',
            'type' => CategoryType::EXPENSE->value,
            'label' => 'Pengeluaran Lainnya',
            'is_system' => true,
            'icon_key' => 'umkm_pengeluaran_lainnya',
            'color_preset_key' => 'umkm_pengeluaran_lainnya',
        ], $templates);
    }

    public function test_custom_category_defaults_follow_tenant_group_fallback_visuals(): void
    {
        $this->assertSame(
            'pf_pemasukan_lainnya',
            CategoryVisualCatalog::defaultIconKeyForCustomCategory(TenantType::PERSONAL, CategoryType::INCOME),
        );
        $this->assertSame(
            'pf_pengeluaran_lainnya',
            CategoryVisualCatalog::defaultColorPresetKeyForCustomCategory(TenantType::FAMILY, CategoryType::EXPENSE),
        );
        $this->assertSame(
            'umkm_pengeluaran_lainnya',
            CategoryVisualCatalog::defaultIconKeyForCustomCategory(TenantType::UMKM, CategoryType::EXPENSE),
        );
        $this->assertSame(
            'tc_operasional_kantor_lainnya',
            CategoryVisualCatalog::defaultColorPresetKeyForCustomCategory(TenantType::COMPANY, CategoryType::EXPENSE),
        );
    }

    public function test_tenant_only_sees_its_own_icon_group(): void
    {
        $personalPresetKeys = CategoryVisualCatalog::allowedIconKeysForTenantType(TenantType::PERSONAL);
        $companyPresetKeys = CategoryVisualCatalog::allowedIconKeysForTenantType(TenantType::COMPANY);

        $this->assertContains('pf_gaji', $personalPresetKeys);
        $this->assertNotContains('umkm_gaji', $personalPresetKeys);
        $this->assertNotContains('tc_konsumsi', $personalPresetKeys);

        $this->assertContains('tc_konsumsi', $companyPresetKeys);
        $this->assertNotContains('pf_gaji', $companyPresetKeys);
    }

    public function test_color_presets_are_available_globally(): void
    {
        $colorPresetKeys = CategoryVisualCatalog::colorPresetKeys();

        $this->assertNotEmpty($colorPresetKeys);
        $this->assertContains('pf_gaji', $colorPresetKeys);
        $this->assertContains('umkm_gaji', $colorPresetKeys);
        $this->assertContains('tc_konsumsi', $colorPresetKeys);
    }
}
