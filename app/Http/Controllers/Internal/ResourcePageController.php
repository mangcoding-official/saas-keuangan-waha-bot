<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResourcePageController extends Controller
{
    private const PAGE_MAP = [
        'tenants.index' => ['title' => 'Tenants', 'description' => 'List/detail tenant akan dipakai support untuk mengecek tenant_status dan service_status.'],
        'users.index' => ['title' => 'Users', 'description' => 'User lintas tenant, activation state, dan alasan akses blokir akan hidup di modul ini.'],
        'verification.index' => ['title' => 'Verification', 'description' => 'Pending verification dan action resend/regenerate akan diikat di milestone 2 dan 9.'],
        'sessions.index' => ['title' => 'Sessions', 'description' => 'Monitoring session guided chat dan timeout akan memakai shell ini.'],
        'transactions.index' => ['title' => 'Transactions', 'description' => 'Read-only support view untuk investigasi transaksi final tenant.'],
        'attachments.index' => ['title' => 'Attachments', 'description' => 'Attachment drawer dan investigasi file proof akan dirender dari modul ini.'],
        'waha.index' => ['title' => 'WAHA Control', 'description' => 'WAHA monitoring, reconnect, dan QR refresh akan dipisah dari controller view menjadi service layer.'],
        'audit.tenant-facing.index' => ['title' => 'Tenant Audit', 'description' => 'Audit domain tenant-facing membedakan aksi tenant user dan super admin.'],
        'audit.platform.index' => ['title' => 'Platform Audit', 'description' => 'Semua action sensitif super admin akan dicatat di audit internal ini.'],
    ];

    public function show(Request $request, string $page): View
    {
        $meta = self::PAGE_MAP[$page];

        return view('internal.resource-page', [
            'page' => [
                'title' => $meta['title'],
                'description' => $meta['description'],
                'eyebrow' => 'Internal Module',
            ],
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => $request->user('platform_admin'),
        ]);
    }
}
