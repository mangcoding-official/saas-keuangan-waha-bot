<?php

namespace App\Support\Navigation;

use App\Enums\UserRole;
use App\Models\TenantUser;

class TenantNavigation
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function items(TenantUser $user): array
    {
        $items = [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'route' => 'tenant.dashboard',
                'pattern' => 'tenant.dashboard',
            ],
            [
                'key' => 'transactions',
                'label' => 'Transaksi',
                'route' => 'tenant.transactions.index',
                'pattern' => 'tenant.transactions.*',
            ],
        ];

        if ($user->role === UserRole::OWNER) {
            return [
                ...$items,
                ['key' => 'accounts', 'label' => 'Akun Keuangan', 'route' => 'tenant.accounts.index', 'pattern' => 'tenant.accounts.*'],
                ['key' => 'categories', 'label' => 'Kategori', 'route' => 'tenant.categories.index', 'pattern' => 'tenant.categories.*'],
                ['key' => 'members', 'label' => 'Anggota', 'route' => 'tenant.members.index', 'pattern' => 'tenant.members.*'],
            ];
        }

        return [
            ...$items,
            ['key' => 'profile', 'label' => 'Profil', 'route' => 'tenant.profile.show', 'pattern' => 'tenant.profile.*'],
        ];
    }
}
