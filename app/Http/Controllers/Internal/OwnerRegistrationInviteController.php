<?php

namespace App\Http\Controllers\Internal;

use App\Enums\InviteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeOwnerRegistrationInviteRequest;
use App\Http\Requests\StoreOwnerRegistrationInviteRequest;
use App\Models\OwnerRegistrationInvite;
use App\Models\PlatformAdminUser;
use App\Services\ActiveBotTargetService;
use App\Services\OwnerRegistrationInviteService;
use App\Services\OwnerRegistrationInviteWhatsappService;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OwnerRegistrationInviteController extends Controller
{
    public function __construct(
        private readonly ActiveBotTargetService $activeBotTargetService,
        private readonly OwnerRegistrationInviteService $ownerRegistrationInviteService,
        private readonly OwnerRegistrationInviteWhatsappService $ownerRegistrationInviteWhatsappService,
    ) {
    }

    public function index(Request $request): View
    {
        $activeBotTarget = $this->activeBotTargetService->resolve();
        $summaryBaseQuery = OwnerRegistrationInvite::query();
        $rows = OwnerRegistrationInvite::query()
            ->leftJoin('platform_admin_users as creators', 'creators.id', '=', 'owner_registration_invites.created_by_platform_admin_user_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'owner_registration_invites.used_by_tenant_id')
            ->orderByDesc('owner_registration_invites.id')
            ->paginate(10, [
                'owner_registration_invites.id',
                'owner_registration_invites.code',
                'owner_registration_invites.status',
                'owner_registration_invites.invited_email',
                'owner_registration_invites.invited_whatsapp_number',
                'owner_registration_invites.invited_whatsapp_number_normalized',
                'owner_registration_invites.note',
                'owner_registration_invites.expires_at',
                'owner_registration_invites.used_at',
                'owner_registration_invites.revoked_at',
                'owner_registration_invites.created_at',
                'creators.name as creator_name',
                'tenants.name as used_tenant_name',
            ])
            ->withQueryString()
            ->through(function (OwnerRegistrationInvite $invite): array {
                if ($invite->status === InviteStatus::PENDING && $invite->expires_at !== null && $invite->expires_at->isPast()) {
                    $invite->status = InviteStatus::EXPIRED;
                }

                return [
                    'id' => $invite->id,
                    'code' => $invite->code,
                    'status_key' => $invite->status->value,
                    'status_label' => match ($invite->status) {
                        InviteStatus::PENDING => 'Pending',
                        InviteStatus::USED => 'Used',
                        InviteStatus::EXPIRED => 'Expired',
                        InviteStatus::REVOKED => 'Revoked',
                    },
                    'invited_email' => $invite->invited_email,
                    'invited_whatsapp_number' => $invite->invited_whatsapp_number,
                    'invited_whatsapp_number_normalized' => $invite->invited_whatsapp_number_normalized,
                    'note' => $invite->note,
                    'expires_at' => $invite->expires_at?->format('d M Y H:i'),
                    'used_at' => $invite->used_at?->format('d M Y H:i'),
                    'revoked_at' => $invite->revoked_at?->format('d M Y H:i'),
                    'created_at' => $invite->created_at?->format('d M Y H:i'),
                    'creator_name' => $invite->creator_name,
                    'used_tenant_name' => $invite->used_tenant_name,
                    'share_url' => route('tenant.register.create', ['invite' => $invite->code]),
                    'can_send_whatsapp' => $invite->status === InviteStatus::PENDING && $invite->invited_whatsapp_number_normalized !== null,
                    'can_revoke' => $invite->status === InviteStatus::PENDING,
                ];
            });

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildInviteDetail($showId) : null;

        return view('internal.invites.index', [
            'page' => [
                'title' => 'Invite User',
                'description' => 'Kelola invite user onboarding, pengiriman WhatsApp, dan status pemakaian kode dari satu halaman.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Onboarding user',
                'search_placeholder' => 'Cari kode invite, email, atau nomor WhatsApp target',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => auth('platform_admin')->user(),
            'summary' => [
                'total' => (int) (clone $summaryBaseQuery)->count(),
                'pending' => (int) (clone $summaryBaseQuery)->where('status', InviteStatus::PENDING->value)->count(),
                'used' => (int) (clone $summaryBaseQuery)->where('status', InviteStatus::USED->value)->count(),
                'blocked' => (int) (clone $summaryBaseQuery)->whereIn('status', [InviteStatus::EXPIRED->value, InviteStatus::REVOKED->value])->count(),
            ],
            'activeBotDisplayNumber' => $activeBotTarget['display_number'] ?? null,
            'rows' => $rows,
            'detail' => $detail,
        ]);
    }

    public function store(StoreOwnerRegistrationInviteRequest $request): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();
        $invite = $this->ownerRegistrationInviteService->createForPlatformAdmin($admin, $request->validated());
        $whatsappSent = $invite->invited_whatsapp_number_normalized !== null
            ? $this->ownerRegistrationInviteWhatsappService->send($invite)
            : false;

        return to_route('internal.invites.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Invite berhasil dibuat',
            'message' => $whatsappSent
                ? 'Kode '.$invite->code.' berhasil dibuat dan langsung dikirim ke WhatsApp target.'
                : 'Kode '.$invite->code.' siap dipakai untuk onboarding alpha.',
        ]);
    }

    public function sendWhatsapp(int $inviteId): RedirectResponse
    {
        $invite = OwnerRegistrationInvite::query()->findOrFail($inviteId);

        if ($invite->status !== InviteStatus::PENDING) {
            return to_route('internal.invites.index')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Invite tidak bisa dikirim',
                'message' => 'Hanya invite pending yang bisa dikirim lewat WhatsApp.',
            ]);
        }

        if ($invite->invited_whatsapp_number_normalized === null) {
            return to_route('internal.invites.index')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Nomor WhatsApp belum tersedia',
                'message' => 'Lengkapi nomor WhatsApp target saat membuat invite baru.',
            ]);
        }

        $sent = $this->ownerRegistrationInviteWhatsappService->send($invite);

        return to_route('internal.invites.index')->with(config('platform.flash_session_key'), [
            'tone' => $sent ? 'success' : 'warning',
            'title' => $sent ? 'Invite berhasil dikirim' : 'Invite belum terkirim',
            'message' => $sent
                ? 'Invite '.$invite->code.' sudah dikirim ke WhatsApp target.'
                : 'Pengiriman invite ke WhatsApp target gagal. Cek bot aktif dan koneksi WAHA.',
        ]);
    }

    public function revoke(RevokeOwnerRegistrationInviteRequest $request, int $inviteId): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();
        $invite = OwnerRegistrationInvite::query()->findOrFail($inviteId);

        $this->ownerRegistrationInviteService->revokeForPlatformAdmin(
            admin: $admin,
            invite: $invite,
            reason: $request->validated('reason'),
        );

        return to_route('internal.invites.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Invite dicabut',
            'message' => 'Invite '.$invite->code.' tidak lagi bisa dipakai untuk registrasi.',
        ]);
    }

    private function buildInviteDetail(int $inviteId): ?array
    {
        $invite = OwnerRegistrationInvite::query()
            ->leftJoin('platform_admin_users as creators', 'creators.id', '=', 'owner_registration_invites.created_by_platform_admin_user_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'owner_registration_invites.used_by_tenant_id')
            ->where('owner_registration_invites.id', $inviteId)
            ->first([
                'owner_registration_invites.id',
                'owner_registration_invites.code',
                'owner_registration_invites.status',
                'owner_registration_invites.invited_email',
                'owner_registration_invites.invited_whatsapp_number',
                'owner_registration_invites.invited_whatsapp_number_normalized',
                'owner_registration_invites.note',
                'owner_registration_invites.expires_at',
                'owner_registration_invites.used_at',
                'owner_registration_invites.revoked_at',
                'owner_registration_invites.created_at',
                'creators.name as creator_name',
                'tenants.name as used_tenant_name',
            ]);

        if ($invite === null) {
            return null;
        }

        if ($invite->status === InviteStatus::PENDING && $invite->expires_at !== null && $invite->expires_at->isPast()) {
            $invite->status = InviteStatus::EXPIRED;
        }

        return [
            'id' => $invite->id,
            'code' => $invite->code,
            'title' => 'Invite '.$invite->code,
            'subtitle' => $invite->invited_email ?: ($invite->invited_whatsapp_number ?: 'Tanpa target spesifik'),
            'badges' => [
                [
                    'label' => match ($invite->status) {
                        InviteStatus::PENDING => 'Pending',
                        InviteStatus::USED => 'Used',
                        InviteStatus::EXPIRED => 'Expired',
                        InviteStatus::REVOKED => 'Revoked',
                    },
                    'tone' => match ($invite->status) {
                        InviteStatus::PENDING => 'success',
                        InviteStatus::USED => 'neutral',
                        InviteStatus::EXPIRED, InviteStatus::REVOKED => 'warning',
                    },
                ],
            ],
            'share_url' => route('tenant.register.create', ['invite' => $invite->code]),
            'can_send_whatsapp' => $invite->status === InviteStatus::PENDING && $invite->invited_whatsapp_number_normalized !== null,
            'can_revoke' => $invite->status === InviteStatus::PENDING,
            'sections' => [
                [
                    'title' => 'Target owner',
                    'lines' => [
                        'Email target: '.($invite->invited_email ?: 'Tidak dibatasi'),
                        'WhatsApp target: '.($invite->invited_whatsapp_number ?: 'Belum diisi'),
                        'Catatan: '.($invite->note ?: '-'),
                    ],
                ],
                [
                    'title' => 'Status invite',
                    'lines' => [
                        'Dibuat: '.($invite->created_at?->format('d M Y H:i') ?: '-'),
                        'Dibuat oleh: '.($invite->creator_name ?: 'System'),
                        'Berlaku sampai: '.($invite->expires_at?->format('d M Y H:i') ?: 'Tanpa batas waktu'),
                    ],
                ],
                [
                    'title' => 'Progress onboarding',
                    'lines' => [
                        'Dipakai tenant: '.($invite->used_tenant_name ?: 'Belum dipakai'),
                        'Waktu dipakai: '.($invite->used_at?->format('d M Y H:i') ?: '-'),
                        'Waktu revoke: '.($invite->revoked_at?->format('d M Y H:i') ?: '-'),
                    ],
                ],
            ],
        ];
    }
}
