<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();
        $latestActivationCode = DB::table('activation_codes')
            ->where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->latest('created_at')
            ->first(['code_last4', 'expires_at']);

        return view('tenant.dashboard', [
            'page' => [
                'title' => 'Tenant overview',
                'description' => 'Dashboard ini sudah memakai tenant scoping dari data login nyata, termasuk status owner, akun default, kategori onboarding, dan readiness activation code.',
                'eyebrow' => strtoupper($user->role->value),
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'stats' => [
                ['label' => 'Users', 'value' => (string) DB::table('tenant_users')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'neutral'],
                ['label' => 'Accounts', 'value' => (string) DB::table('accounts')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'success'],
                ['label' => 'Categories', 'value' => (string) DB::table('categories')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'neutral'],
                ['label' => 'Transactions', 'value' => (string) DB::table('transactions')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'success'],
                ['label' => 'Active Sessions', 'value' => (string) DB::table('conversation_sessions')->where('tenant_id', $user->tenant_id)->where('status', 'active')->count(), 'tone' => 'warning'],
            ],
            'tenantSummary' => [
                'tenant_name' => $user->tenant->name,
                'tenant_type' => $user->tenant->tenant_type->value,
                'timezone' => $user->tenant->timezone,
                'service_status' => $user->tenant->service_status->value,
                'verification_status' => $user->verification_status->value,
                'owner_email' => $user->email,
                'owner_whatsapp' => $user->whatsapp_number,
                'active_code_last4' => $latestActivationCode?->code_last4,
                'active_code_expires_at' => $latestActivationCode?->expires_at,
                'dashboard_role_note' => $user->role === UserRole::OWNER
                    ? 'Owner melihat seluruh konteks tenant miliknya sendiri.'
                    : 'Member hanya melihat konteks transaksi dan profil miliknya sendiri.',
            ],
        ]);
    }
}
