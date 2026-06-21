<?php

namespace App\Http\Controllers\Internal;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdminUser;
use App\Models\TenantUser;
use App\Services\TenantVerificationCodeService;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VerificationController extends Controller
{
    public function __construct(
        private readonly TenantVerificationCodeService $tenantVerificationCodeService,
    ) {
    }

    public function index(): View
    {
        $rows = DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->leftJoin('activation_codes', function ($join): void {
                $join->on('activation_codes.tenant_user_id', '=', 'tenant_users.id')
                    ->where('activation_codes.active_lock', '=', 1);
            })
            ->leftJoin('tenant_users as inviters', 'inviters.id', '=', 'tenant_users.invited_by_user_id')
            ->where('tenant_users.verification_status', VerificationStatus::PENDING_VERIFICATION->value)
            ->orderByDesc('tenant_users.created_at')
            ->get([
                'tenant_users.id',
                'tenant_users.name',
                'tenant_users.role',
                'tenant_users.user_status',
                'tenant_users.whatsapp_number',
                'tenant_users.created_at',
                'tenants.name as tenant_name',
                'activation_codes.code_last4',
                'activation_codes.expires_at',
                'activation_codes.status as activation_status',
                'inviters.name as inviter_name',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'tenant_name' => $row->tenant_name,
                'name' => $row->name,
                'role' => strtoupper((string) $row->role),
                'user_status' => (string) $row->user_status,
                'whatsapp_number' => $row->whatsapp_number,
                'created_at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
                'activation_last4' => $row->code_last4 ? 'KAS-'.$row->code_last4 : null,
                'activation_status' => $row->expires_at && Carbon::parse($row->expires_at)->isPast()
                    ? 'expired'
                    : ((string) ($row->activation_status ?? 'none')),
                'activation_expires_at' => $row->expires_at ? Carbon::parse($row->expires_at)->format('d M Y H:i') : null,
                'inviter_name' => $row->inviter_name ?: 'Website Registration',
            ])
            ->all();

        return view('internal.verification.index', [
            'page' => [
                'title' => 'Verification',
                'description' => 'Super admin memonitor semua user pending verification dan dapat melakukan resend atau regenerate activation code.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari tenant, user, atau nomor WhatsApp',
                'search_placeholder' => 'Search tenant, user, or WhatsApp number',
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
                'total_pending' => count($rows),
                'pending_owners' => count(array_filter($rows, fn (array $row): bool => $row['role'] === 'OWNER')),
                'pending_members' => count(array_filter($rows, fn (array $row): bool => $row['role'] === 'MEMBER')),
                'expired_codes' => count(array_filter($rows, fn (array $row): bool => $row['activation_status'] === 'expired')),
            ],
            'rows' => $rows,
        ]);
    }

    public function resend(int $tenantUserId): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();
        $targetUser = $this->findTargetUser($tenantUserId);
        $code = $this->tenantVerificationCodeService->resendForPlatformAdmin($admin, $targetUser);

        return to_route('internal.verification.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Activation code dikirim ulang',
            'message' => 'Code baru '.$code.' sekarang aktif untuk '.$targetUser->name.'.',
        ]);
    }

    public function regenerate(int $tenantUserId): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();
        $targetUser = $this->findTargetUser($tenantUserId);
        $code = $this->tenantVerificationCodeService->regenerateForPlatformAdmin($admin, $targetUser);

        return to_route('internal.verification.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Activation code diregenerate',
            'message' => 'Code lama dibatalkan dan code baru '.$code.' sekarang aktif untuk '.$targetUser->name.'.',
        ]);
    }

    private function findTargetUser(int $tenantUserId): TenantUser
    {
        return TenantUser::query()->findOrFail($tenantUserId);
    }
}
