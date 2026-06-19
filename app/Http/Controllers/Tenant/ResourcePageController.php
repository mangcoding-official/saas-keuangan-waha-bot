<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResourcePageController extends Controller
{
    private const PAGE_MAP = [
        'transactions' => [
            'title' => 'Transactions',
            'description' => 'List, filter, detail panel, dan action akan diisi bertahap mulai milestone 6.',
            'state_title' => 'Belum ada transaksi foundation',
        ],
        'accounts' => [
            'title' => 'Accounts',
            'description' => 'Manajemen akun keuangan owner akan mulai hidup pada milestone 5.',
            'state_title' => 'Master account belum diaktifkan',
        ],
        'categories' => [
            'title' => 'Categories',
            'description' => 'Kategori dan keyword parser akan mengikuti contract schema final pada milestone 5.',
            'state_title' => 'Kategori masih kosong',
        ],
        'members' => [
            'title' => 'Members',
            'description' => 'Owner akan mengundang member dan mengelola verification state pada milestone 2.',
            'state_title' => 'Belum ada member yang ditampilkan',
        ],
        'audit' => [
            'title' => 'Audit',
            'description' => 'Audit tenant-facing akan diaktifkan setelah flow edit, void, dan attachment berjalan.',
            'state_title' => 'Audit belum memiliki event',
        ],
        'settings' => [
            'title' => 'Settings',
            'description' => 'Tenant settings akan menampung konfigurasi layanan dan profil tenant.',
            'state_title' => 'Settings shell siap dipakai',
        ],
        'profile' => [
            'title' => 'Profile',
            'description' => 'Member profile akan menjadi titik awal akses dashboard personal.',
            'state_title' => 'Profile data akan diisi dari tenant user',
        ],
    ];

    public function show(Request $request, string $page): View
    {
        /** @var TenantUser $user */
        $user = $request->user('web');
        $meta = self::PAGE_MAP[$page];

        return view('tenant.resource-page', [
            'page' => [
                'title' => $meta['title'],
                'description' => $meta['description'],
                'eyebrow' => 'Tenant Module',
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'stateTitle' => $meta['state_title'],
        ]);
    }
}
