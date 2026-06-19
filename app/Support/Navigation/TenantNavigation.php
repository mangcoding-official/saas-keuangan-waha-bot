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
                'label' => 'Overview',
                'route' => 'tenant.dashboard',
                'pattern' => 'tenant.dashboard',
            ],
            [
                'label' => 'Transactions',
                'route' => 'tenant.transactions.index',
                'pattern' => 'tenant.transactions.*',
            ],
        ];

        if ($user->role === UserRole::OWNER) {
            return [
                ...$items,
                ['label' => 'Accounts', 'route' => 'tenant.accounts.index', 'pattern' => 'tenant.accounts.*'],
                ['label' => 'Categories', 'route' => 'tenant.categories.index', 'pattern' => 'tenant.categories.*'],
                ['label' => 'Members', 'route' => 'tenant.members.index', 'pattern' => 'tenant.members.*'],
                ['label' => 'Audit', 'route' => 'tenant.audit.index', 'pattern' => 'tenant.audit.*'],
                ['label' => 'Settings', 'route' => 'tenant.settings.index', 'pattern' => 'tenant.settings.*'],
            ];
        }

        return [
            ...$items,
            ['label' => 'Profile', 'route' => 'tenant.profile.show', 'pattern' => 'tenant.profile.*'],
        ];
    }
}
