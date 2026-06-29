<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantMemberRequest;
use App\Models\TenantUser;
use App\Services\TenantMemberInvitationService;
use App\Services\TenantVerificationCodeService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function __construct(
        private readonly TenantMemberInvitationService $tenantMemberInvitationService,
        private readonly TenantVerificationCodeService $tenantVerificationCodeService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $tenantId = $owner->tenant_id;
        $limit = (int) config('platform.limits.tenant_users');
        $search = trim((string) $request->query('search', ''));
        $createRequested = $request->boolean('create') || $request->session()->hasOldInput();

        $baseQuery = DB::table('tenant_users')
            ->leftJoin('activation_codes', function ($join): void {
                $join->on('activation_codes.tenant_user_id', '=', 'tenant_users.id')
                    ->where('activation_codes.active_lock', '=', 1);
            })
            ->leftJoin('tenant_users as inviters', 'inviters.id', '=', 'tenant_users.invited_by_user_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($memberQuery) use ($search): void {
                    $memberQuery
                        ->where('tenant_users.name', 'like', '%'.$search.'%')
                        ->orWhere('tenant_users.email', 'like', '%'.$search.'%')
                        ->orWhere('tenant_users.whatsapp_number', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw("CASE WHEN tenant_users.role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('tenant_users.created_at');

        $memberColumns = [
            'tenant_users.id',
            'tenant_users.name',
            'tenant_users.email',
            'tenant_users.role',
            'tenant_users.user_status',
            'tenant_users.verification_status',
            'tenant_users.whatsapp_number',
            'tenant_users.last_login_at',
            'tenant_users.created_at',
            'activation_codes.code_last4',
            'activation_codes.expires_at',
            'activation_codes.status as activation_status',
            'inviters.name as inviter_name',
        ];

        $allMembers = (clone $baseQuery)
            ->get([
                ...$memberColumns,
            ])
            ->map(fn (object $member): array => $this->mapMemberRow($member, $owner))
            ->all();

        $memberPaginator = (clone $baseQuery)
            ->paginate(3, $memberColumns)
            ->withQueryString();

        $members = $memberPaginator->getCollection()
            ->map(fn (object $member): array => $this->mapMemberRow($member, $owner))
            ->all();

        $totalUsers = count($allMembers);
        $ownerCount = count(array_filter($allMembers, fn (array $member): bool => $member['role_key'] === UserRole::OWNER->value));
        $memberCount = count(array_filter($allMembers, fn (array $member): bool => $member['role_key'] === UserRole::MEMBER->value));
        $pendingCount = count(array_filter($allMembers, fn (array $member): bool => $member['verification_status_key'] === VerificationStatus::PENDING_VERIFICATION->value));
        $remainingSlots = max(0, $limit - $totalUsers);

        return view('tenant.members.index', [
            'page' => [
                'title' => '',
                'html_title' => 'Manajemen Anggota',
                'description' => '',
                'eyebrow' => '',
            ],
            'toolbar' => [
                'search_label' => 'Cari anggota',
                'search_placeholder' => 'Cari anggota...',
                'search_action' => route('tenant.members.index'),
                'search_name' => 'search',
                'search_value' => $search,
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'summary' => [
                'total_users' => $totalUsers,
                'owner_count' => $ownerCount,
                'member_count' => $memberCount,
                'pending_count' => $pendingCount,
                'remaining_slots' => $remainingSlots,
                'limit' => $limit,
            ],
            'slotIsFull' => $remainingSlots === 0,
            'members' => $members,
            'memberPaginator' => $memberPaginator,
            'activeSearch' => $search,
            'memberModal' => $createRequested ? [
                'close_url' => route('tenant.members.index', $request->except(['create'])),
            ] : null,
        ]);
    }

    public function store(StoreTenantMemberRequest $request): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $result = $this->tenantMemberInvitationService->invite($owner, $request->validated());
        $deliveryNote = $result['whatsapp_sent']
            ? ' Activation code juga sudah dikirim ke WhatsApp user.'
            : ' Namun pesan belum berhasil dikirim.';

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Member berhasil ditambahkan',
            'message' => 'Activation code '.$result['activation_code'].' dibuat untuk '.$result['member']->name.'. Status member sekarang pending verification.'.$deliveryNote,
        ]);
    }

    public function resend(int $memberId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $member = $this->findManagedUser($owner, $memberId);
        $result = $this->tenantVerificationCodeService->resendForTenantOwner($owner, $member);
        $deliveryNote = $result['whatsapp_sent']
            ? ' sudah dikirim.'
            : ' Namun pesan belum berhasil dikirim.';

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Activation code dikirim ulang',
            'message' => 'Code baru '.$result['code'].' sekarang aktif untuk '.$member->name.'. Code aktif sebelumnya otomatis tidak berlaku.'.$deliveryNote,
        ]);
    }

    public function regenerate(int $memberId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $member = $this->findManagedUser($owner, $memberId);
        $result = $this->tenantVerificationCodeService->regenerateForTenantOwner($owner, $member);
        $deliveryNote = $result['whatsapp_sent']
            ? ' sudah dikirim.'
            : ' Namun pesan belum berhasil dikirim.';

        return to_route('tenant.members.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Activation code diregenerate',
            'message' => 'Code baru '.$result['code'].' untuk '.$member->name.'.'.$deliveryNote,
        ]);
    }

    private function findManagedUser(TenantUser $owner, int $memberId): TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', $owner->tenant_id)
            ->whereIn('role', [UserRole::OWNER, UserRole::MEMBER])
            ->findOrFail($memberId);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMemberRow(object $member, TenantUser $owner): array
    {
        $role = (string) $member->role;
        $userStatus = (string) $member->user_status;
        $verificationStatus = (string) $member->verification_status;
        $lastLoginAt = $member->last_login_at
            ? Carbon::parse($member->last_login_at)->timezone($owner->tenant->timezone)->locale('id')->diffForHumans()
            : 'Belum pernah';

        return [
            'id' => (int) $member->id,
            'name' => $member->name,
            'email' => $member->email ?: 'Belum ada email',
            'initials' => $this->memberInitials((string) $member->name),
            'role_key' => $role,
            'role' => $role === UserRole::OWNER->value ? 'Owner' : 'Member',
            'whatsapp_number' => $this->maskWhatsappNumber((string) $member->whatsapp_number),
            'user_status_key' => $userStatus,
            'user_status_label' => $userStatus === 'active' ? 'Aktif' : 'Nonaktif',
            'verification_status_key' => $verificationStatus,
            'verification_status_label' => $verificationStatus === VerificationStatus::VERIFIED->value ? 'Terverifikasi' : 'Menunggu',
            'created_at' => Carbon::parse($member->created_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            'last_active_at' => $lastLoginAt,
            'activation_last4' => $member->code_last4 ? 'KAS-'.$member->code_last4 : null,
            'activation_expires_at' => $member->expires_at
                ? Carbon::parse($member->expires_at)->timezone($owner->tenant->timezone)->format('d M Y H:i')
                : null,
            'activation_status' => $member->expires_at && Carbon::parse($member->expires_at)->isPast()
                ? 'expired'
                : ((string) ($member->activation_status ?? 'none')),
            'inviter_name' => $member->inviter_name ?: 'Website Registration',
            'can_manage_code' => $verificationStatus === VerificationStatus::PENDING_VERIFICATION->value,
        ];
    }

    private function memberInitials(string $name): string
    {
        $segments = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($segments)
            ->filter()
            ->take(2)
            ->map(fn (string $segment): string => strtoupper(substr($segment, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'U';
    }

    private function maskWhatsappNumber(string $whatsappNumber): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $whatsappNumber) ?? '';

        if ($digitsOnly === '') {
            return $whatsappNumber;
        }

        if (strlen($digitsOnly) <= 6) {
            return $digitsOnly;
        }

        $prefix = substr($digitsOnly, 0, 4);
        $suffix = substr($digitsOnly, -4);
        $maskedLength = max(2, strlen($digitsOnly) - 8);

        return $prefix.str_repeat('*', $maskedLength).$suffix;
    }
}
