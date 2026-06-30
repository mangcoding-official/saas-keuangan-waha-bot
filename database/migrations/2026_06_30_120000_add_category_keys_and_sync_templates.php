<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('key', 120)->nullable()->after('type');
            $table->unique(['tenant_id', 'type', 'key'], 'categories_tenant_type_key_unique');
        });

        $tenants = DB::table('tenants')
            ->select(['id', 'tenant_type'])
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $this->syncTenantCategories((int) $tenant->id, (string) $tenant->tenant_type);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique('categories_tenant_type_key_unique');
            $table->dropColumn('key');
        });
    }

    private function syncTenantCategories(int $tenantId, string $tenantType): void
    {
        $templates = $this->finalTemplates($tenantType);
        $templatesByTypeAndLabel = [];
        $templatesByTypeAndKey = [];
        $requiredKeys = [];

        foreach ($templates as $template) {
            $templatesByTypeAndLabel[$template['type']][$this->normalizeLabel($template['label'])] = $template;
            $templatesByTypeAndKey[$template['type']][$template['key']] = $template;

            if ($template['is_system']) {
                $requiredKeys[$template['type']] = $template['key'];
            }
        }

        $categories = DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();

        $usedKeys = [];
        $presentTemplateKeys = [];

        foreach ($categories as $category) {
            $type = (string) $category->type;
            $label = (string) $category->name;
            $normalizedLabel = $this->normalizeLabel($label);
            $matchedTemplate = $templatesByTypeAndLabel[$type][$normalizedLabel] ?? null;
            $updates = [];

            if ($matchedTemplate !== null && ! isset($presentTemplateKeys[$type][$matchedTemplate['key']])) {
                $updates['key'] = $matchedTemplate['key'];
                $updates['name'] = $matchedTemplate['label'];
                $updates['is_system'] = $matchedTemplate['is_system'];

                if ($matchedTemplate['is_system']) {
                    $updates['is_active'] = true;
                }

                $presentTemplateKeys[$type][$matchedTemplate['key']] = true;
                $usedKeys[$type][$matchedTemplate['key']] = true;
            } else {
                $generatedKey = $this->uniqueKeyForLabel($usedKeys, $type, $label);
                $updates['key'] = $generatedKey;
                $usedKeys[$type][$generatedKey] = true;

                if (
                    in_array($label, $this->legacyLabels($tenantType), true)
                    && ! isset($templatesByTypeAndLabel[$type][$normalizedLabel])
                ) {
                    $updates['is_active'] = false;
                    $updates['is_system'] = false;
                }
            }

            DB::table('categories')
                ->where('id', $category->id)
                ->update($updates);
        }

        foreach ($templates as $template) {
            if (isset($presentTemplateKeys[$template['type']][$template['key']])) {
                continue;
            }

            DB::table('categories')->insert([
                'tenant_id' => $tenantId,
                'type' => $template['type'],
                'key' => $template['key'],
                'name' => $template['label'],
                'keywords' => null,
                'is_system' => $template['is_system'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($requiredKeys as $type => $requiredKey) {
            DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('key', $requiredKey)
                ->update([
                    'is_system' => true,
                    'is_active' => true,
                ]);
        }
    }

    /**
     * @return array<int, array{key: string, type: string, label: string, is_system: bool}>
     */
    private function finalTemplates(string $tenantType): array
    {
        $pack = match ($tenantType) {
            'personal', 'family' => 'personal_family',
            'umkm' => 'umkm',
            'team', 'company' => 'team',
            default => 'personal_family',
        };

        return match ($pack) {
            'personal_family' => [
                ['key' => 'salary', 'type' => 'income', 'label' => 'Gaji', 'is_system' => false],
                ['key' => 'sales', 'type' => 'income', 'label' => 'Penjualan', 'is_system' => false],
                ['key' => 'profit_sharing', 'type' => 'income', 'label' => 'Bagi Hasil', 'is_system' => false],
                ['key' => 'investment', 'type' => 'income', 'label' => 'Investasi', 'is_system' => false],
                ['key' => 'top_up', 'type' => 'income', 'label' => 'Top Up', 'is_system' => false],
                ['key' => 'other_income', 'type' => 'income', 'label' => 'Pemasukan Lainnya', 'is_system' => true],
                ['key' => 'work_needs', 'type' => 'expense', 'label' => 'Kebutuhan Pekerjaan', 'is_system' => false],
                ['key' => 'basic_needs', 'type' => 'expense', 'label' => 'Kebutuhan Pokok', 'is_system' => false],
                ['key' => 'health', 'type' => 'expense', 'label' => 'Kesehatan', 'is_system' => false],
                ['key' => 'transportation', 'type' => 'expense', 'label' => 'Transportasi', 'is_system' => false],
                ['key' => 'installment', 'type' => 'expense', 'label' => 'Cicilan', 'is_system' => false],
                ['key' => 'entertainment', 'type' => 'expense', 'label' => 'Hiburan', 'is_system' => false],
                ['key' => 'bills', 'type' => 'expense', 'label' => 'Tagihan', 'is_system' => false],
                ['key' => 'savings', 'type' => 'expense', 'label' => 'Tabungan', 'is_system' => false],
                ['key' => 'donation', 'type' => 'expense', 'label' => 'Donasi', 'is_system' => false],
                ['key' => 'education', 'type' => 'expense', 'label' => 'Pendidikan', 'is_system' => false],
                ['key' => 'investment', 'type' => 'expense', 'label' => 'Investasi', 'is_system' => false],
                ['key' => 'top_up', 'type' => 'expense', 'label' => 'Top Up', 'is_system' => false],
                ['key' => 'shopping', 'type' => 'expense', 'label' => 'Belanja', 'is_system' => false],
                ['key' => 'other_expense', 'type' => 'expense', 'label' => 'Pengeluaran Lainnya', 'is_system' => true],
            ],
            'umkm' => [
                ['key' => 'sales', 'type' => 'income', 'label' => 'Penjualan', 'is_system' => false],
                ['key' => 'commission_profit_sharing', 'type' => 'income', 'label' => 'Komisi & Bagi Hasil', 'is_system' => false],
                ['key' => 'investment_return', 'type' => 'income', 'label' => 'Return Investasi', 'is_system' => false],
                ['key' => 'savings_interest', 'type' => 'income', 'label' => 'Bunga Tabungan', 'is_system' => false],
                ['key' => 'other_income', 'type' => 'income', 'label' => 'Pendapatan Lainnya', 'is_system' => true],
                ['key' => 'raw_materials', 'type' => 'expense', 'label' => 'Bahan Baku', 'is_system' => false],
                ['key' => 'shipping_cost', 'type' => 'expense', 'label' => 'Ongkos Kirim', 'is_system' => false],
                ['key' => 'production_operations', 'type' => 'expense', 'label' => 'Operasional Produksi', 'is_system' => false],
                ['key' => 'office_operations', 'type' => 'expense', 'label' => 'Operasional Kantor', 'is_system' => false],
                ['key' => 'salary', 'type' => 'expense', 'label' => 'Gaji', 'is_system' => false],
                ['key' => 'bonus', 'type' => 'expense', 'label' => 'Bonus', 'is_system' => false],
                ['key' => 'advertising', 'type' => 'expense', 'label' => 'Iklan', 'is_system' => false],
                ['key' => 'endorsement', 'type' => 'expense', 'label' => 'Endorsement', 'is_system' => false],
                ['key' => 'affiliate_commission', 'type' => 'expense', 'label' => 'Komisi Afiliasi', 'is_system' => false],
                ['key' => 'transportation', 'type' => 'expense', 'label' => 'Transportasi', 'is_system' => false],
                ['key' => 'rent', 'type' => 'expense', 'label' => 'Sewa', 'is_system' => false],
                ['key' => 'administration', 'type' => 'expense', 'label' => 'Administrasi', 'is_system' => false],
                ['key' => 'tax', 'type' => 'expense', 'label' => 'Pajak', 'is_system' => false],
                ['key' => 'other_expense', 'type' => 'expense', 'label' => 'Pengeluaran Lainnya', 'is_system' => true],
            ],
            'team' => [
                ['key' => 'operational_top_up', 'type' => 'income', 'label' => 'Top Up Dana Operasional', 'is_system' => false],
                ['key' => 'office_reimbursement', 'type' => 'income', 'label' => 'Reimbursement Kantor', 'is_system' => false],
                ['key' => 'vendor_refund', 'type' => 'income', 'label' => 'Refund Vendor', 'is_system' => false],
                ['key' => 'other_income', 'type' => 'income', 'label' => 'Pemasukan Lainnya', 'is_system' => true],
                ['key' => 'transportation_travel', 'type' => 'expense', 'label' => 'Transportasi & Perjalanan', 'is_system' => false],
                ['key' => 'consumption', 'type' => 'expense', 'label' => 'Konsumsi', 'is_system' => false],
                ['key' => 'atk_office_supplies', 'type' => 'expense', 'label' => 'ATK & Keperluan Kantor', 'is_system' => false],
                ['key' => 'communication', 'type' => 'expense', 'label' => 'Komunikasi', 'is_system' => false],
                ['key' => 'courier_shipping', 'type' => 'expense', 'label' => 'Kurir / Pengiriman', 'is_system' => false],
                ['key' => 'entertainment', 'type' => 'expense', 'label' => 'Entertainment', 'is_system' => false],
                ['key' => 'promotion_sales_activation', 'type' => 'expense', 'label' => 'Promosi & Aktivasi Penjualan', 'is_system' => false],
                ['key' => 'client_meeting_cost', 'type' => 'expense', 'label' => 'Biaya Meeting Klien', 'is_system' => false],
                ['key' => 'field_visit', 'type' => 'expense', 'label' => 'Kunjungan Lapangan', 'is_system' => false],
                ['key' => 'recruitment', 'type' => 'expense', 'label' => 'Recruitment', 'is_system' => false],
                ['key' => 'employee_welfare', 'type' => 'expense', 'label' => 'Employee Welfare', 'is_system' => false],
                ['key' => 'housekeeping_pantry', 'type' => 'expense', 'label' => 'Housekeeping & Pantry', 'is_system' => false],
                ['key' => 'repair_maintenance', 'type' => 'expense', 'label' => 'Perbaikan & Pemeliharaan', 'is_system' => false],
                ['key' => 'other_office_operations', 'type' => 'expense', 'label' => 'Operasional Kantor Lainnya', 'is_system' => true],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function legacyLabels(string $tenantType): array
    {
        return match ($tenantType) {
            'personal' => ['Gaji', 'Bonus', 'Makan', 'Transport', 'Biaya Admin'],
            'family' => ['Pemasukan Keluarga', 'Belanja Rumah', 'Pendidikan', 'Tagihan', 'Biaya Admin'],
            'umkm' => ['Penjualan', 'Piutang Masuk', 'Belanja Stok', 'Operasional', 'Biaya Admin'],
            'team' => ['Iuran Tim', 'Sponsor', 'Operasional Tim', 'Event', 'Biaya Admin'],
            'company' => ['Pendapatan Penjualan', 'Pendapatan Lain', 'Biaya Operasional', 'Gaji', 'Biaya Admin'],
            default => [],
        };
    }

    /**
     * @param  array<string, array<string, bool>>  $usedKeys
     */
    private function uniqueKeyForLabel(array &$usedKeys, string $type, string $label): string
    {
        $baseKey = Str::of($label)
            ->lower()
            ->slug('_')
            ->value();

        if ($baseKey === '') {
            $baseKey = 'category';
        }

        $candidate = $baseKey;
        $suffix = 2;

        while (isset($usedKeys[$type][$candidate])) {
            $candidate = $baseKey.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label) ?? $label));
    }
};
