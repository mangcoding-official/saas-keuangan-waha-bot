<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('internal.dashboard', [
            'page' => [
                'title' => 'Platform overview',
                'description' => 'Overview internal memusatkan status tenant, verification, session, dan WAHA dalam satu shell operasional.',
                'eyebrow' => 'Support Operations',
            ],
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => auth('platform_admin')->user(),
            'stats' => [
                ['label' => 'Tenants', 'value' => (string) DB::table('tenants')->count(), 'tone' => 'neutral'],
                ['label' => 'Pending Verification', 'value' => (string) DB::table('tenant_users')->where('verification_status', 'pending_verification')->count(), 'tone' => 'warning'],
                ['label' => 'WAHA Instances', 'value' => (string) DB::table('bot_instances')->count(), 'tone' => 'success'],
                ['label' => 'Active Sessions', 'value' => (string) DB::table('conversation_sessions')->where('status', 'active')->count(), 'tone' => 'neutral'],
            ],
        ]);
    }
}
