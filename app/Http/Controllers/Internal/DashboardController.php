<?php

namespace App\Http\Controllers\Internal;

use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\VerificationStatus;
use App\Enums\WahaConnectionStatus;
use App\Http\Controllers\Controller;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeTenants = (int) DB::table('tenants')
            ->where('tenant_status', TenantStatus::ACTIVE->value)
            ->where('service_status', ServiceStatus::ACTIVE->value)
            ->count();

        $suspendedTenants = (int) DB::table('tenants')
            ->where('service_status', ServiceStatus::SUSPENDED->value)
            ->count();

        $endedTenants = (int) DB::table('tenants')
            ->where('service_status', ServiceStatus::ENDED->value)
            ->count();

        $inactiveTenants = (int) DB::table('tenants')
            ->where('tenant_status', TenantStatus::INACTIVE->value)
            ->count();

        $pendingVerification = (int) DB::table('tenant_users')
            ->where('verification_status', VerificationStatus::PENDING_VERIFICATION->value)
            ->count();

        $warningTenants = (int) DB::table('tenant_users')
            ->where('verification_status', VerificationStatus::PENDING_VERIFICATION->value)
            ->distinct()
            ->count('tenant_id');

        $wahaWarnings = (int) DB::table('bot_instances')
            ->whereIn('connection_status', [
                WahaConnectionStatus::DISCONNECTED->value,
                WahaConnectionStatus::ERROR->value,
            ])
            ->count();

        $botInstancesCount = (int) DB::table('bot_instances')->count();
        $serviceIssues = $suspendedTenants + $endedTenants + $inactiveTenants;

        $staleSessions = (int) DB::table('conversation_sessions')
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->count();

        $expiredCodes = (int) DB::table('activation_codes')
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->count();

        $webhookWarnings = (int) DB::table('bot_instances')
            ->whereNotNull('webhook_status')
            ->where('webhook_status', '<>', 'healthy')
            ->count();

        $attentionQueue = DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->where('tenant_users.verification_status', VerificationStatus::PENDING_VERIFICATION->value)
            ->orderByDesc('tenant_users.created_at')
            ->limit(4)
            ->get([
                'tenants.name as tenant_name',
                'tenant_users.name as user_name',
                'tenant_users.user_status',
            ])
            ->map(fn (object $row): array => [
                'queue' => 'Pending verification',
                'tenant_user' => $row->tenant_name.' / '.$row->user_name,
                'status' => $row->user_status === 'inactive' ? 'Blocked' : 'Warning',
                'next_action' => 'Review verification',
            ])
            ->all();

        $recentInternalActions = DB::table('platform_admin_audit_logs')
            ->join('platform_admin_users', 'platform_admin_users.id', '=', 'platform_admin_audit_logs.platform_admin_user_id')
            ->orderByDesc('platform_admin_audit_logs.created_at')
            ->limit(4)
            ->get([
                'platform_admin_audit_logs.action',
                'platform_admin_audit_logs.target_entity_type',
                'platform_admin_audit_logs.created_at',
                'platform_admin_users.name as actor_name',
            ])
            ->map(fn (object $row): array => [
                'action' => str($row->action)->replace('_', ' ')->title()->toString(),
                'actor' => $row->actor_name,
                'target' => str($row->target_entity_type)->replace('_', ' ')->title()->toString(),
                'time' => $row->created_at ? date('H:i', strtotime((string) $row->created_at)) : '-',
            ])
            ->all();

        $wahaHealthLabel = $botInstancesCount === 0
            ? 'Offline'
            : ($wahaWarnings > 0 || $webhookWarnings > 0 ? 'Warning' : 'Healthy');

        $wahaHealthNote = $botInstancesCount === 0
            ? 'Belum ada bot instance aktif'
            : ($wahaWarnings > 0 || $webhookWarnings > 0
                ? ($wahaWarnings + $webhookWarnings).' issue perlu ditindak'
                : 'Semua instance dalam kondisi stabil');

        return view('internal.dashboard', [
            'page' => [
                'title' => 'Platform overview',
                'description' => 'Monitor tenant, verifikasi, bot WAHA, dan isu operasional platform dalam satu layar.',
                'eyebrow' => 'Support Operations',
            ],
            'toolbar' => [
                'search_label' => 'Cari tenant, user, session, atau group',
                'search_placeholder' => 'Search tenant, user, session, or message',
                'secondary_action' => [
                    'label' => 'Open WAHA',
                    'href' => route('internal.waha.index'),
                    'variant' => 'secondary',
                ],
                'primary_action' => [
                    'label' => 'Review queue',
                    'href' => route('internal.verification.index'),
                    'variant' => 'primary',
                ],
            ],
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => auth('platform_admin')->user(),
            'kpis' => [
                [
                    'label' => 'Tenant active',
                    'value' => (string) $activeTenants,
                    'note' => $inactiveTenants.' tenant inactive',
                    'tone' => 'neutral',
                ],
                [
                    'label' => 'Service issues',
                    'value' => (string) $serviceIssues,
                    'note' => 'Suspended, ended, atau tenant inactive',
                    'tone' => $serviceIssues > 0 ? 'alert' : 'neutral',
                ],
                [
                    'label' => 'Pending verification',
                    'value' => (string) $pendingVerification,
                    'note' => 'Butuh follow-up support hari ini',
                    'tone' => $pendingVerification > 0 ? 'alert' : 'neutral',
                ],
                [
                    'label' => 'WAHA health',
                    'value' => $wahaHealthLabel,
                    'note' => $wahaHealthNote,
                    'tone' => $wahaHealthLabel === 'Healthy' ? 'neutral' : 'alert',
                ],
            ],
            'attentionQueueSummary' => [
                'Pending verification: '.$pendingVerification,
                'Session restart needed: '.$staleSessions,
                'WAHA warning: '.($wahaWarnings + $webhookWarnings),
                'Tenant suspended: '.$suspendedTenants,
            ],
            'attentionQueueRows' => $attentionQueue,
            'platformStatus' => [
                'Active tenants: '.$activeTenants,
                'Warning tenants: '.$warningTenants,
                'Suspended tenants: '.$suspendedTenants,
                'Ended tenants: '.$endedTenants,
            ],
            'statusBadge' => $wahaWarnings + $webhookWarnings > 0 ? 'waha warning' : 'waha healthy',
            'verificationSnapshot' => [
                'Action items: '.$pendingVerification,
                'Expired codes: '.$expiredCodes,
                'Stale sessions: '.$staleSessions,
            ],
            'serviceSnapshot' => [
                'Active tenant: '.$activeTenants,
                'Suspended: '.$suspendedTenants,
                'Ended: '.$endedTenants,
                'Webhook warning: '.$webhookWarnings,
            ],
            'recentInternalActions' => $recentInternalActions,
        ]);
    }
}
