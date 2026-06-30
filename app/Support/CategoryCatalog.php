<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\TenantType;

class CategoryCatalog
{
    /**
     * @return array<int, array{key: string, type: string, label: string, is_system: bool, preset_key: string}>
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
     * @return array<int, array{key: string, type: string, label: string, is_system: bool, preset_key: string}>
     */
    private static function templatesForPack(string $pack): array
    {
        return match ($pack) {
            'personal_family' => [
                ['key' => 'salary', 'type' => CategoryType::INCOME->value, 'label' => 'Gaji', 'is_system' => false, 'preset_key' => 'pf_gaji'],
                ['key' => 'sales', 'type' => CategoryType::INCOME->value, 'label' => 'Penjualan', 'is_system' => false, 'preset_key' => 'pf_penjualan'],
                ['key' => 'profit_sharing', 'type' => CategoryType::INCOME->value, 'label' => 'Bagi Hasil', 'is_system' => false, 'preset_key' => 'pf_bagi_hasil'],
                ['key' => 'investment', 'type' => CategoryType::INCOME->value, 'label' => 'Investasi', 'is_system' => false, 'preset_key' => 'pf_investasi'],
                ['key' => 'top_up', 'type' => CategoryType::INCOME->value, 'label' => 'Top Up', 'is_system' => false, 'preset_key' => 'pf_top_up'],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pemasukan Lainnya', 'is_system' => true, 'preset_key' => 'pf_pemasukan_lainnya'],
                ['key' => 'work_needs', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kebutuhan Pekerjaan', 'is_system' => false, 'preset_key' => 'pf_kebutuhan_pekerjaan'],
                ['key' => 'basic_needs', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kebutuhan Pokok', 'is_system' => false, 'preset_key' => 'pf_kebutuhan_pokok'],
                ['key' => 'health', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kesehatan', 'is_system' => false, 'preset_key' => 'pf_kesehatan'],
                ['key' => 'transportation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi', 'is_system' => false, 'preset_key' => 'pf_transportasi'],
                ['key' => 'installment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Cicilan', 'is_system' => false, 'preset_key' => 'pf_cicilan'],
                ['key' => 'entertainment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Hiburan', 'is_system' => false, 'preset_key' => 'pf_hiburan'],
                ['key' => 'bills', 'type' => CategoryType::EXPENSE->value, 'label' => 'Tagihan', 'is_system' => false, 'preset_key' => 'pf_tagihan'],
                ['key' => 'savings', 'type' => CategoryType::EXPENSE->value, 'label' => 'Tabungan', 'is_system' => false, 'preset_key' => 'pf_tabungan'],
                ['key' => 'donation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Donasi', 'is_system' => false, 'preset_key' => 'pf_donasi'],
                ['key' => 'education', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pendidikan', 'is_system' => false, 'preset_key' => 'pf_pendidikan'],
                ['key' => 'investment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Investasi', 'is_system' => false, 'preset_key' => 'pf_investasi'],
                ['key' => 'top_up', 'type' => CategoryType::EXPENSE->value, 'label' => 'Top Up', 'is_system' => false, 'preset_key' => 'pf_top_up'],
                ['key' => 'shopping', 'type' => CategoryType::EXPENSE->value, 'label' => 'Belanja', 'is_system' => false, 'preset_key' => 'pf_belanja'],
                ['key' => 'other_expense', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pengeluaran Lainnya', 'is_system' => true, 'preset_key' => 'pf_pengeluaran_lainnya'],
            ],
            'umkm' => [
                ['key' => 'sales', 'type' => CategoryType::INCOME->value, 'label' => 'Penjualan', 'is_system' => false, 'preset_key' => 'umkm_penjualan'],
                ['key' => 'commission_profit_sharing', 'type' => CategoryType::INCOME->value, 'label' => 'Komisi & Bagi Hasil', 'is_system' => false, 'preset_key' => 'umkm_komisi_bagi_hasil'],
                ['key' => 'investment_return', 'type' => CategoryType::INCOME->value, 'label' => 'Return Investasi', 'is_system' => false, 'preset_key' => 'umkm_return_investasi'],
                ['key' => 'savings_interest', 'type' => CategoryType::INCOME->value, 'label' => 'Bunga Tabungan', 'is_system' => false, 'preset_key' => 'umkm_bunga_tabungan'],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pendapatan Lainnya', 'is_system' => true, 'preset_key' => 'umkm_pendapatan_lainnya'],
                ['key' => 'raw_materials', 'type' => CategoryType::EXPENSE->value, 'label' => 'Bahan Baku', 'is_system' => false, 'preset_key' => 'umkm_bahan_baku'],
                ['key' => 'shipping_cost', 'type' => CategoryType::EXPENSE->value, 'label' => 'Ongkos Kirim', 'is_system' => false, 'preset_key' => 'umkm_ongkos_kirim'],
                ['key' => 'production_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Produksi', 'is_system' => false, 'preset_key' => 'umkm_operasional_produksi'],
                ['key' => 'office_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Kantor', 'is_system' => false, 'preset_key' => 'umkm_operasional_kantor'],
                ['key' => 'salary', 'type' => CategoryType::EXPENSE->value, 'label' => 'Gaji', 'is_system' => false, 'preset_key' => 'umkm_gaji'],
                ['key' => 'bonus', 'type' => CategoryType::EXPENSE->value, 'label' => 'Bonus', 'is_system' => false, 'preset_key' => 'umkm_bonus'],
                ['key' => 'advertising', 'type' => CategoryType::EXPENSE->value, 'label' => 'Iklan', 'is_system' => false, 'preset_key' => 'umkm_iklan'],
                ['key' => 'endorsement', 'type' => CategoryType::EXPENSE->value, 'label' => 'Endorsement', 'is_system' => false, 'preset_key' => 'umkm_endorsement'],
                ['key' => 'affiliate_commission', 'type' => CategoryType::EXPENSE->value, 'label' => 'Komisi Afiliasi', 'is_system' => false, 'preset_key' => 'umkm_komisi_afiliasi'],
                ['key' => 'transportation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi', 'is_system' => false, 'preset_key' => 'umkm_transportasi'],
                ['key' => 'rent', 'type' => CategoryType::EXPENSE->value, 'label' => 'Sewa', 'is_system' => false, 'preset_key' => 'umkm_sewa'],
                ['key' => 'administration', 'type' => CategoryType::EXPENSE->value, 'label' => 'Administrasi', 'is_system' => false, 'preset_key' => 'umkm_administrasi'],
                ['key' => 'tax', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pajak', 'is_system' => false, 'preset_key' => 'umkm_pajak'],
                ['key' => 'other_expense', 'type' => CategoryType::EXPENSE->value, 'label' => 'Pengeluaran Lainnya', 'is_system' => true, 'preset_key' => 'umkm_pengeluaran_lainnya'],
            ],
            'team' => [
                ['key' => 'operational_top_up', 'type' => CategoryType::INCOME->value, 'label' => 'Top Up Dana Operasional', 'is_system' => false, 'preset_key' => 'tc_top_up_dana_operasional'],
                ['key' => 'office_reimbursement', 'type' => CategoryType::INCOME->value, 'label' => 'Reimbursement Kantor', 'is_system' => false, 'preset_key' => 'tc_reimbursement_kantor'],
                ['key' => 'vendor_refund', 'type' => CategoryType::INCOME->value, 'label' => 'Refund Vendor', 'is_system' => false, 'preset_key' => 'tc_refund_vendor'],
                ['key' => 'other_income', 'type' => CategoryType::INCOME->value, 'label' => 'Pemasukan Lainnya', 'is_system' => true, 'preset_key' => 'tc_pemasukan_lainnya'],
                ['key' => 'transportation_travel', 'type' => CategoryType::EXPENSE->value, 'label' => 'Transportasi & Perjalanan', 'is_system' => false, 'preset_key' => 'tc_transportasi_perjalanan'],
                ['key' => 'consumption', 'type' => CategoryType::EXPENSE->value, 'label' => 'Konsumsi', 'is_system' => false, 'preset_key' => 'tc_konsumsi'],
                ['key' => 'atk_office_supplies', 'type' => CategoryType::EXPENSE->value, 'label' => 'ATK & Keperluan Kantor', 'is_system' => false, 'preset_key' => 'tc_atk_keperluan_kantor'],
                ['key' => 'communication', 'type' => CategoryType::EXPENSE->value, 'label' => 'Komunikasi', 'is_system' => false, 'preset_key' => 'tc_komunikasi'],
                ['key' => 'courier_shipping', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kurir / Pengiriman', 'is_system' => false, 'preset_key' => 'tc_kurir_pengiriman'],
                ['key' => 'entertainment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Entertainment', 'is_system' => false, 'preset_key' => 'tc_entertainment'],
                ['key' => 'promotion_sales_activation', 'type' => CategoryType::EXPENSE->value, 'label' => 'Promosi & Aktivasi Penjualan', 'is_system' => false, 'preset_key' => 'tc_promosi_aktivasi_penjualan'],
                ['key' => 'client_meeting_cost', 'type' => CategoryType::EXPENSE->value, 'label' => 'Biaya Meeting Klien', 'is_system' => false, 'preset_key' => 'tc_biaya_meeting_klien'],
                ['key' => 'field_visit', 'type' => CategoryType::EXPENSE->value, 'label' => 'Kunjungan Lapangan', 'is_system' => false, 'preset_key' => 'tc_kunjungan_lapangan'],
                ['key' => 'recruitment', 'type' => CategoryType::EXPENSE->value, 'label' => 'Recruitment', 'is_system' => false, 'preset_key' => 'tc_recruitment'],
                ['key' => 'employee_welfare', 'type' => CategoryType::EXPENSE->value, 'label' => 'Employee Welfare', 'is_system' => false, 'preset_key' => 'tc_employee_welfare'],
                ['key' => 'housekeeping_pantry', 'type' => CategoryType::EXPENSE->value, 'label' => 'Housekeeping & Pantry', 'is_system' => false, 'preset_key' => 'tc_housekeeping_pantry'],
                ['key' => 'repair_maintenance', 'type' => CategoryType::EXPENSE->value, 'label' => 'Perbaikan & Pemeliharaan', 'is_system' => false, 'preset_key' => 'tc_perbaikan_pemeliharaan'],
                ['key' => 'other_office_operations', 'type' => CategoryType::EXPENSE->value, 'label' => 'Operasional Kantor Lainnya', 'is_system' => true, 'preset_key' => 'tc_operasional_kantor_lainnya'],
            ],
            default => [],
        };
    }
}
