<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantMemberRequest;
use App\Models\TenantUser;
use App\Services\TenantMemberInvitationService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function __construct(
        private readonly TenantMemberInvitationService $tenantMemberInvitationService,
    ) {
    }

    public function index(): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $tenantId = $owner->tenant_id;
        $limit = (int) config('platform.limits.tenant_users');

        $members = DB::table('tenant_users')
            ->leftJoin('activation_codes', function ($join): void {
                $join->on('activation_codes.tenant_user_id', '=', 'tenant_users.id')
                    ->where('activation_codes.active_lock', '=', 1);
            })
            ->leftJoin('tenant_users as inviters', 'inviters.id', '=', 'tenant_users.invited_by_user_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->orderByRaw("CASE WHEN tenant_users.role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('tenant_users.created_at')
            ->get([
                'tenant_users.id',
                'tenant_users.name',
                'tenant_users.role',
                'tenant_users.user_status',
                'tenant_users.verification_status',
                'tenant_users.whatsapp_number',
                'tenant_users.created_at',
                'activation_codes.code_last4',
                'activation_codes.expires_at',
                'activation_codes.status as activation_status',
                'inviters.name as inviter_name',
            ])
            ->map(fn (object $member): array => [
                'id' => (int) $member->id,
                'name' => $member->name,
                'role' => strtoupper((string) $member->role),
                'user_status' => (string) $member->user_status,
                'verification_status' => (string) $member->verification_status,
                'whatsapp_number' => $member->whatsapp_number,
                'created_at' => Carbon::parse($member->created_at)->format('d M Y H:i'),
                'activation_last4' => $member->code_last4 ? 'KAS-'.$member->code_last4 : null,
                'activation_expires_at' => $member->expires_at
                    ? Carbon::parse($member->expires_at)->timezone($owner->tenant->timezone)->format('d M Y H:i')
                    : null,
                'activation_status' => $member->expires_at && Carbon::parse($member->expires_at)->isPast()
                    ? 'expired'
                    : ((string) ($member->activation_status ?? 'none')),
                'inviter_name' => $member->inviter_name ?: 'Website Registration',
                'can_manage_code' => $member->role === UserRole::MEMBER->value
                    && $member->verification_status === VerificationStatus::PENDING_VERIFICATION->value,
            ])
            ->all();

        $totalUsers = count($members);
        $memberCount = count(array_filter($members, fn (array $member): bool => $member['role'] === 'MEMBER'));
        $pendingCount = count(array_filter($members, fn (array $member): bool => $member['verification_status'] === VerificationStatus::PENDING_VERIFICATION->value));
        $remainingSlots = max(0, $limit - $totalUsers);

        return view('tenant.members.index', [
            'page' => [
                'title' => 'Members',
                'description' => 'Owner menambah member, memonitor status verifikasi, dan mengelola activation code.',
                'eyebrow' => 'Tenant Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari member atau nomor WhatsApp',
                'search_placeholder' => 'Search member or WhatsApp number',
                'secondary_action' => [
                    'label' => 'Back to overview',
                    'href' => route('tenant.dashboard'),
                    'variant' => 'secondary',
                ],
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'summary' => [
                'total_users' => $totalUsers,
                'member_count' => $memberCount,
                'pending_count' => $pendingCount,
                'remaining_slots' => $remainingSlots,
                'limit' => $limit,
            ],
            'slotIsFull' => $remainingSlots === 0,
            'members' => $members,
        ]);
    }

    public function store(StoreTenantMemberRequest $request): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $result = $this->tenantMemberInvitationService->invite($owner, $request->validated());

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Member berhasil ditambahkan',
            'message' => 'Activation code '.$result['activation_code'].' dibuat untuk '.$result['member']->name.'. Status member sekarang pending verification.',
        ]);
    }

    public function resend(int $memberId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $member = $this->findMember($owner, $memberId);
        $activationCode = $this->tenantMemberInvitationService->resend($owner, $member);

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Activation code dikirim ulang',
            'message' => 'Code baru '.$activationCode.' sekarang aktif untuk '.$member->name.'. Code aktif sebelumnya otomatis tidak berlaku.',
        ]);
    }

    public function regenerate(int $memberId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $member = $this->findMember($owner, $memberId);
        $activationCode = $this->tenantMemberInvitationService->regenerate($owner, $member);

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Activation code diregenerate',
            'message' => 'Code lama dibatalkan dan code baru '.$activationCode.' sekarang aktif untuk '.$member->name.'.',
        ]);
    }

    private function findMember(TenantUser $owner, int $memberId): TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', $owner->tenant_id)
            ->where('role', UserRole::MEMBER)
            ->findOrFail($memberId);
    }
}
