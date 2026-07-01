<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupportInboxController extends Controller
{
    public function index(): View
    {
        $selectedId = (int) request()->query('show', 0);

        $rows = DB::table('tenant_support_requests')
            ->join('tenants', 'tenants.id', '=', 'tenant_support_requests.tenant_id')
            ->join('tenant_users', 'tenant_users.id', '=', 'tenant_support_requests.tenant_user_id')
            ->orderByDesc('tenant_support_requests.created_at')
            ->get([
                'tenant_support_requests.id',
                'tenant_support_requests.subject',
                'tenant_support_requests.message',
                'tenant_support_requests.status',
                'tenant_support_requests.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as sender_name',
                'tenant_users.whatsapp_number',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'tenant_name' => (string) $row->tenant_name,
                'sender_name' => (string) $row->sender_name,
                'whatsapp_number' => (string) $row->whatsapp_number,
                'subject' => (string) $row->subject,
                'message' => (string) $row->message,
                'status' => strtoupper((string) $row->status),
                'created_at_raw' => Carbon::parse($row->created_at),
                'created_at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
            ])
            ->all();

        $selectedMessage = collect($rows)->firstWhere('id', $selectedId);

        return view('internal.support.index', [
            'page' => [
                'title' => 'Support Inbox',
                'description' => 'Daftar pesan yang masuk dari halaman Bantuan & Feedback tenant.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Pantau pesan bantuan tenant',
                'search_placeholder' => 'Inbox support tenant',
                'secondary_action' => [
                    'label' => 'Back to overview',
                    'href' => route('internal.dashboard'),
                    'variant' => 'secondary',
                ],
                'primary_action' => null,
            ],
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => auth('platform_admin')->user(),
            'summary' => [
                'total_messages' => count($rows),
                'new_messages' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'NEW')),
                'unique_tenants' => count(array_unique(array_map(fn (array $row): string => $row['tenant_name'], $rows))),
                'today_messages' => count(array_filter($rows, fn (array $row): bool => $row['created_at_raw']->isToday())),
            ],
            'rows' => $rows,
            'messageModal' => $selectedMessage ? [
                'message' => $selectedMessage,
                'close_url' => route('internal.support.index', request()->except(['show'])),
            ] : null,
        ]);
    }
}
