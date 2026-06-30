<?php

namespace Tests\Unit;

use App\Enums\CategoryType;
use App\Enums\TenantType;
use App\Support\CategoryCatalog;
use PHPUnit\Framework\TestCase;

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
        ], $templates);
        $this->assertContains([
            'key' => 'other_expense',
            'type' => CategoryType::EXPENSE->value,
            'label' => 'Pengeluaran Lainnya',
            'is_system' => true,
        ], $templates);
    }
}
