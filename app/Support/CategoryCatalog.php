<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\TenantType;

class CategoryCatalog
{
    /**
     * @return array<int, array{key: string, type: string, label: string, is_system: bool}>
     */
    public static function templatesForTenantType(TenantType $tenantType): array
    {
        return self::templatesForPack(self::packForTenantType($tenantType));
    }

    public static function fallbackKeyFor(TenantType $tenantType, CategoryType $type): string
    {
        $templates = self::templatesForTenantType($tenantType);

        foreach ($templates as $template) {
            if ($template['type'] === $type->value && $template['is_system']) {
                return $template['key'];
            }
        }

        throw new \RuntimeException('Fallback category key is not configured for tenant type '.$tenantType->value.'.');
    }

    public static function packForTenantType(TenantType $tenantType): string
    {
        return match ($tenantType) {
            TenantType::PERSONAL, TenantType::FAMILY => 'personal_family',
            TenantType::UMKM => 'umkm',
            TenantType::TEAM, TenantType::COMPANY => 'team',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function legacyLabelsForTenantType(TenantType $tenantType): array
    {
        return match ($tenantType) {
            TenantType::PERSONAL => ['Gaji', 'Bonus', 'Makan', 'Transport', 'Biaya Admin'],
            TenantType::FAMILY => ['Pemasukan Keluarga', 'Belanja Rumah', 'Pendidikan', 'Tagihan', 'Biaya Admin'],
            TenantType::UMKM => ['Penjualan', 'Piutang Masuk', 'Belanja Stok', 'Operasional', 'Biaya Admin'],
            TenantType::TEAM => ['Iuran Tim', 'Sponsor', 'Operasional Tim', 'Event', 'Biaya Admin'],
            TenantType::COMPANY => ['Pendapatan Penjualan', 'Pendapatan Lain', 'Biaya Operasional', 'Gaji', 'Biaya Admin'],
        };
    }

    /**
     * @return array<int, array{key: string, type: string, label: string, is_system: bool}>
     */
    private static function templatesForPack(string $pack): array
    {
        return match ($pack) {
            'personal_family' => [
                ['key' => 'salary', 'type' => CategoryType::INCOME->value, 'label' => 'Gaji', 'is_system' => false],
                ['key' => 'sales', 'type' => CategoryType::INCOME->value, 'label' => 'Penjualan', 'is_system' => false],
                ['key' => 'profit_sharing', 'type' => CategoryType::INCOME->value, 'label' => 'Bagi Hasil', 'is_system' => false],
                ['key' => 'investment', 'type' => CategoryType::INCOME->value, 'label' => 'Investasi', 'is_system' => false],
                ['key' => 'top_up', 'type' => CategoryType::INCOME->value, 'label' => 'Top Up', 'is_system' => false],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pemasukan Lainnya', 'is_system' => true],
                ['key' => 'work_needs', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kebutuhan Pekerjaan', 'is_system' => false],
                ['key' => 'basic_needs', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kebutuhan Pokok', 'is_system' => false],
                ['key' => 'health', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kesehatan', 'is_system' => false],
                ['key' => 'transportation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi', 'is_system' => false],
                ['key' => 'installment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Cicilan', 'is_system' => false],
                ['key' => 'entertainment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Hiburan', 'is_system' => false],
                ['key' => 'bills', 'type' => CategoryType::EXPENSE->value, 'label' => 'Tagihan', 'is_system' => false],
                ['key' => 'savings', 'type' => CategoryType::EXPENSE->value, 'label' => 'Tabungan', 'is_system' => false],
                ['key' => 'donation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Donasi', 'is_system' => false],
                ['key' => 'education', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pendidikan', 'is_system' => false],
                ['key' => 'investment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Investasi', 'is_system' => false],
                ['key' => 'top_up', 'type' => CategoryType::EXPENSE->value, 'label' => 'Top Up', 'is_system' => false],
                ['key' => 'shopping', 'type' => CategoryType::EXPENSE->value, 'label' => 'Belanja', 'is_system' => false],
                ['key' => 'other_expense', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pengeluaran Lainnya', 'is_system' => true],
            ],
            'umkm' => [
                ['key' => 'sales', 'type' => CategoryType::INCOME->value, 'label' => 'Penjualan', 'is_system' => false],
                ['key' => 'commission_profit_sharing', 'type' => CategoryType::INCOME->value, 'label' => 'Komisi & Bagi Hasil', 'is_system' => false],
                ['key' => 'investment_return', 'type' => CategoryType::INCOME->value, 'label' => 'Return Investasi', 'is_system' => false],
                ['key' => 'savings_interest', 'type' => CategoryType::INCOME->value, 'label' => 'Bunga Tabungan', 'is_system' => false],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pendapatan Lainnya', 'is_system' => true],
                ['key' => 'raw_materials', 'type' => CategoryType::EXPENSE->value, 'label' => 'Bahan Baku', 'is_system' => false],
                ['key' => 'shipping_cost', 'type' => CategoryType::EXPENSE->value, 'label' => 'Ongkos Kirim', 'is_system' => false],
                ['key' => 'production_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Produksi', 'is_system' => false],
                ['key' => 'office_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Kantor', 'is_system' => false],
                ['key' => 'salary', 'type' => CategoryType::EXPENSE->value, 'label' => 'Gaji', 'is_system' => false],
                ['key' => 'bonus', 'type' => CategoryType::EXPENSE->value, 'label' => 'Bonus', 'is_system' => false],
                ['key' => 'advertising', 'type' => CategoryType::EXPENSE->value, 'label' => 'Iklan', 'is_system' => false],
                ['key' => 'endorsement', 'type' => CategoryType::EXPENSE->value, 'label' => 'Endorsement', 'is_system' => false],
                ['key' => 'affiliate_commission', 'type' => CategoryType::EXPENSE->value, 'label' => 'Komisi Afiliasi', 'is_system' => false],
                ['key' => 'transportation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi', 'is_system' => false],
                ['key' => 'rent', 'type' => CategoryType::EXPENSE->value, 'label' => 'Sewa', 'is_system' => false],
                ['key' => 'administration', 'type' => CategoryType::EXPENSE->value, 'label' => 'Administrasi', 'is_system' => false],
                ['key' => 'tax', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pajak', 'is_system' => false],
                ['key' => 'other_expense', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pengeluaran Lainnya', 'is_system' => true],
            ],
            'team' => [
                ['key' => 'operational_top_up', 'type' => CategoryType::INCOME->value, 'label' => 'Top Up Dana Operasional', 'is_system' => false],
                ['key' => 'office_reimbursement', 'type' => CategoryType::INCOME->value, 'label' => 'Reimbursement Kantor', 'is_system' => false],
                ['key' => 'vendor_refund', 'type' => CategoryType::INCOME->value, 'label' => 'Refund Vendor', 'is_system' => false],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pemasukan Lainnya', 'is_system' => true],
                ['key' => 'transportation_travel', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi & Perjalanan', 'is_system' => false],
                ['key' => 'consumption', 'type' => CategoryType::EXPENSE->value, 'label' => 'Konsumsi', 'is_system' => false],
                ['key' => 'atk_office_supplies', 'type' => CategoryType::EXPENSE->value, 'label' => 'ATK & Keperluan Kantor', 'is_system' => false],
                ['key' => 'communication', 'type' => CategoryType::EXPENSE->value, 'label' => 'Komunikasi', 'is_system' => false],
                ['key' => 'courier_shipping', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kurir / Pengiriman', 'is_system' => false],
                ['key' => 'entertainment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Entertainment', 'is_system' => false],
                ['key' => 'promotion_sales_activation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Promosi & Aktivasi Penjualan', 'is_system' => false],
                ['key' => 'client_meeting_cost', 'type' => CategoryType::EXPENSE->value, 'label' => 'Biaya Meeting Klien', 'is_system' => false],
                ['key' => 'field_visit', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kunjungan Lapangan', 'is_system' => false],
                ['key' => 'recruitment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Recruitment', 'is_system' => false],
                ['key' => 'employee_welfare', 'type' => CategoryType::EXPENSE->value, 'label' => 'Employee Welfare', 'is_system' => false],
                ['key' => 'housekeeping_pantry', 'type' => CategoryType::EXPENSE->value, 'label' => 'Housekeeping & Pantry', 'is_system' => false],
                ['key' => 'repair_maintenance', 'type' => CategoryType::EXPENSE->value, 'label' => 'Perbaikan & Pemeliharaan', 'is_system' => false],
                ['key' => 'other_office_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Kantor Lainnya', 'is_system' => true],
            ],
            default => [],
        };
    }
}
