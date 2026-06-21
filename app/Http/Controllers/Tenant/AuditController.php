<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AuditController extends Controller
{
    public function index(): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $timezone = $owner->tenant->timezone;

        $auditLogs = AuditLog::query()
            ->with('actorTenantUser')
            ->where('tenant_id', $owner->tenant_id)
            ->where('entity_type', 'transaction')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (AuditLog $log) use ($timezone): array {
                return [
                    'id' => $log->id,
                    'created_at' => Carbon::parse($log->created_at)->timezone($timezone)->format('d M Y H:i'),
                    'actor' => $log->actorTenantUser?->name ?? 'System',
                    'action_label' => $this->actionLabel($log->action),
                    'target' => 'Transaction #'.$log->entity_id,
                    'summary' => $this->buildSummaryLines($log->action, $log->before_payload, $log->after_payload),
                ];
            })
            ->all();

        $updatedCount = collect($auditLogs)->where('action_label', 'Transaction updated')->count();
        $voidedCount = collect($auditLogs)->where('action_label', 'Transaction voided')->count();

        return view('tenant.audit.index', [
            'page' => [
                'title' => 'Audit',
                'description' => 'Tinjau perubahan transaksi.',
                'eyebrow' => '',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Search audit logs',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'summary' => [
                'total' => count($auditLogs),
                'updated' => $updatedCount,
                'voided' => $voidedCount,
            ],
            'auditLogs' => $auditLogs,
        ]);
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'transaction_updated' => 'Transaction updated',
            'transaction_voided' => 'Transaction voided',
            default => str($action)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @param  array<string, mixed>|null  $beforePayload
     * @param  array<string, mixed>|null  $afterPayload
     * @return array<int, string>
     */
    private function buildSummaryLines(string $action, ?array $beforePayload, ?array $afterPayload): array
    {
        if ($action === 'transaction_voided') {
            return array_values(array_filter([
                'Status: '.strtoupper((string) ($beforePayload['status'] ?? 'completed')).' -> '.strtoupper((string) ($afterPayload['status'] ?? 'void')),
                isset($afterPayload['void_reason']) ? 'Alasan: '.$afterPayload['void_reason'] : null,
                isset($afterPayload['amount']) ? 'Nominal: Rp '.number_format((float) $afterPayload['amount'], 0, ',', '.') : null,
            ]));
        }

        $labels = [
            'amount' => 'Nominal',
            'transaction_date' => 'Tanggal',
            'category_name' => 'Kategori',
            'source_account_name' => 'Akun sumber',
            'destination_account_name' => 'Akun tujuan',
            'description' => 'Deskripsi',
        ];

        $lines = [];

        foreach ($labels as $key => $label) {
            $beforeValue = $beforePayload[$key] ?? null;
            $afterValue = $afterPayload[$key] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            if ($key === 'amount') {
                $beforeValue = 'Rp '.number_format((float) $beforeValue, 0, ',', '.');
                $afterValue = 'Rp '.number_format((float) $afterValue, 0, ',', '.');
            }

            $lines[] = $label.': '.($beforeValue ?: '-').' -> '.($afterValue ?: '-');
        }

        return $lines === [] ? ['Snapshot transaksi diperbarui tanpa perubahan field terdeteksi.'] : $lines;
    }
}
