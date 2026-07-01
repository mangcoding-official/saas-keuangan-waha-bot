<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var TenantUser $user */
        $user = $request->user('web');
        $user->loadMissing('tenant', 'invitedBy');

        $tenant = $user->tenant;
        $tenantUsers = $tenant->users();
        $workspaceUserCount = (clone $tenantUsers)->count();
        $pendingUserCount = (clone $tenantUsers)
            ->where('verification_status', VerificationStatus::PENDING_VERIFICATION)
            ->count();
        $verifiedUserCount = max(0, $workspaceUserCount - $pendingUserCount);

        return view('tenant.profile.show', [
            'page' => [
                'title' => 'Profil Saya',
                'description' => 'Ringkasan akun dan workspace yang sedang Anda gunakan.',
                'eyebrow' => '',
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'profile' => [
                'name' => $user->name,
                'initials' => $this->memberInitials($user->name),
                'role' => $user->role === UserRole::OWNER ? 'Owner' : 'Member',
                'email' => $user->email ?: 'Belum ada email',
                'whatsapp_number' => $this->maskWhatsappNumber((string) $user->whatsapp_number),
                'user_status_label' => $user->user_status->value === 'active' ? 'Akun aktif' : 'Akun nonaktif',
                'verification_status_label' => $user->verification_status === VerificationStatus::VERIFIED ? 'WhatsApp terverifikasi' : 'Menunggu verifikasi WhatsApp',
                'joined_at' => $this->formatDateTime($user->created_at, $tenant->timezone),
                'verified_at' => $user->verified_at ? $this->formatDateTime($user->verified_at, $tenant->timezone) : 'Belum diverifikasi',
                'last_login_at' => $user->last_login_at ? Carbon::parse($user->last_login_at)->timezone($tenant->timezone)->locale('id')->diffForHumans() : 'Belum pernah login ulang',
                'invited_by' => $user->invitedBy?->name ?? 'Registrasi website',
            ],
            'workspace' => [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'tenant_type' => $this->formatLabel($tenant->tenant_type->value),
                'tenant_status' => $tenant->tenant_status->value === 'active' ? 'Workspace aktif' : 'Workspace nonaktif',
                'service_plan' => strtoupper($tenant->service_plan->value),
                'service_status' => $tenant->service_status->value === 'active' ? 'Layanan aktif' : $this->formatLabel($tenant->service_status->value),
                'ai_addon_status' => $tenant->ai_addon_status->value === 'active' ? 'AI addon aktif' : 'AI addon belum aktif',
                'workspace_user_count' => $workspaceUserCount,
                'verified_user_count' => $verifiedUserCount,
                'pending_user_count' => $pendingUserCount,
            ],
        ]);
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

    private function formatDateTime(Carbon $value, string $timezone): string
    {
        return $value->copy()->timezone($timezone)->format('d M Y H:i');
    }

    private function formatLabel(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->value();
    }
}
