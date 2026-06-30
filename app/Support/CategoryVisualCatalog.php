<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\TenantType;

class CategoryVisualCatalog
{
    /**
     * @return array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>
     */
    public static function allPresets(): array
    {
        return [
            ...self::groupPresets('personal_family', 'Personal & Family', [
                'pf_gaji' => 'Gaji',
                'pf_penjualan' => 'Penjualan',
                'pf_bagi_hasil' => 'Bagi Hasil',
                'pf_investasi' => 'Investasi',
                'pf_top_up' => 'Top Up',
                'pf_pemasukan_lainnya' => 'Pemasukan Lainnya',
                'pf_kebutuhan_pekerjaan' => 'Kebutuhan Pekerjaan',
                'pf_kebutuhan_pokok' => 'Kebutuhan Pokok',
                'pf_kesehatan' => 'Kesehatan',
                'pf_transportasi' => 'Transportasi',
                'pf_cicilan' => 'Cicilan',
                'pf_hiburan' => 'Hiburan',
                'pf_tagihan' => 'Tagihan',
                'pf_tabungan' => 'Tabungan',
                'pf_donasi' => 'Donasi',
                'pf_pendidikan' => 'Pendidikan',
                'pf_belanja' => 'Belanja',
                'pf_pengeluaran_lainnya' => 'Pengeluaran Lainnya',
            ]),
            ...self::groupPresets('team_company', 'Teams & Company', [
                'tc_top_up_dana_operasional' => 'Top Up Dana Operasional',
                'tc_reimbursement_kantor' => 'Reimbursement Kantor',
                'tc_refund_vendor' => 'Refund Vendor',
                'tc_pemasukan_lainnya' => 'Pemasukan Lainnya',
                'tc_transportasi_perjalanan' => 'Transportasi & Perjalanan',
                'tc_konsumsi' => 'Konsumsi',
                'tc_atk_keperluan_kantor' => 'ATK & Keperluan Kantor',
                'tc_komunikasi' => 'Komunikasi',
                'tc_kurir_pengiriman' => 'Kurir / Pengiriman',
                'tc_entertainment' => 'Entertainment',
                'tc_promosi_aktivasi_penjualan' => 'Promosi & Aktivasi Penjualan',
                'tc_biaya_meeting_klien' => 'Biaya Meeting Klien',
                'tc_kunjungan_lapangan' => 'Kunjungan Lapangan',
                'tc_recruitment' => 'Recruitment',
                'tc_employee_welfare' => 'Employee Welfare',
                'tc_housekeeping_pantry' => 'Housekeeping & Pantry',
                'tc_perbaikan_pemeliharaan' => 'Perbaikan & Pemeliharaan',
                'tc_operasional_kantor_lainnya' => 'Operasional Kantor Lainnya',
            ]),
            ...self::groupPresets('umkm', 'UMKM', [
                'umkm_penjualan' => 'Penjualan',
                'umkm_komisi_bagi_hasil' => 'Komisi & Bagi Hasil',
                'umkm_return_investasi' => 'Return Investasi',
                'umkm_bunga_tabungan' => 'Bunga Tabungan',
                'umkm_pendapatan_lainnya' => 'Pendapatan Lainnya',
                'umkm_bahan_baku' => 'Bahan Baku',
                'umkm_ongkos_kirim' => 'Ongkos Kirim',
                'umkm_operasional_produksi' => 'Operasional Produksi',
                'umkm_operasional_kantor' => 'Operasional Kantor',
                'umkm_gaji' => 'Gaji',
                'umkm_bonus' => 'Bonus',
                'umkm_iklan' => 'Iklan',
                'umkm_endorsement' => 'Endorsement',
                'umkm_komisi_afiliasi' => 'Komisi Afiliasi',
                'umkm_transportasi' => 'Transportasi',
                'umkm_sewa' => 'Sewa',
                'umkm_administrasi' => 'Administrasi',
                'umkm_pajak' => 'Pajak',
                'umkm_pengeluaran_lainnya' => 'Pengeluaran Lainnya',
            ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function presetKeys(): array
    {
        return array_column(self::allPresets(), 'key');
    }

    public static function hasPresetKey(?string $key): bool
    {
        return is_string($key) && in_array($key, self::presetKeys(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedPresetKeysForTenantType(TenantType $tenantType): array
    {
        return array_column(self::presetsForTenantType($tenantType), 'key');
    }

    /**
     * @return array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}|null
     */
    public static function findPreset(?string $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (self::allPresets() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{group_key: string, group_label: string, is_recommended: bool, presets: array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>}>
     */
    public static function groupedPresetsForTenantType(TenantType $tenantType): array
    {
        $recommendedGroup = self::groupForTenantType($tenantType);
        $presets = self::presetsForTenantType($tenantType);

        if ($presets === []) {
            return [];
        }

        return [[
            'group_key' => $recommendedGroup,
            'group_label' => $presets[0]['tenant_group_label'],
            'is_recommended' => true,
            'presets' => $presets,
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

    public static function defaultPresetKeyForCustomCategory(TenantType $tenantType, CategoryType $categoryType): string
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

    /**
     * @param  array<string, string>  $presets
     * @return array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>
     */
    private static function groupPresets(string $tenantGroup, string $tenantGroupLabel, array $presets): array
    {
        $rows = [];

        foreach ($presets as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'tenant_group' => $tenantGroup,
                'tenant_group_label' => $tenantGroupLabel,
                'asset_path' => 'images/category-presets/'.$key.'.png',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{key: string, label: string, tenant_group: string, tenant_group_label: string, asset_path: string}>
     */
    private static function presetsForTenantType(TenantType $tenantType): array
    {
        $group = self::groupForTenantType($tenantType);

        return array_values(array_filter(
            self::allPresets(),
            fn (array $preset): bool => $preset['tenant_group'] === $group,
        ));
    }
}
