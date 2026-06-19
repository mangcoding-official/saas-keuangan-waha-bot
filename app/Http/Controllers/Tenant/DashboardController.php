<?php

namespace App\Http\Controllers\Tenant;

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

        return view('tenant.dashboard', [
            'page' => [
                'title' => 'Tenant overview',
                'description' => 'Shell ini menjadi dasar dashboard owner dan member. Data memakai tenant scope dari guard web.',
                'eyebrow' => strtoupper($user->role->value),
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'stats' => [
                ['label' => 'Users', 'value' => (string) DB::table('tenant_users')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'neutral'],
                ['label' => 'Transactions', 'value' => (string) DB::table('transactions')->where('tenant_id', $user->tenant_id)->count(), 'tone' => 'success'],
                ['label' => 'Active Sessions', 'value' => (string) DB::table('conversation_sessions')->where('tenant_id', $user->tenant_id)->where('status', 'active')->count(), 'tone' => 'warning'],
            ],
        ]);
    }
}
