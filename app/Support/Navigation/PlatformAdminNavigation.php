<?php

namespace App\Support\Navigation;

class PlatformAdminNavigation
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Overview', 'route' => 'internal.dashboard', 'pattern' => 'internal.dashboard'],
            ['label' => 'Tenants', 'route' => 'internal.tenants.index', 'pattern' => 'internal.tenants.*'],
            ['label' => 'Users', 'route' => 'internal.users.index', 'pattern' => 'internal.users.*'],
            ['label' => 'Invites', 'route' => 'internal.invites.index', 'pattern' => 'internal.invites.*'],
            ['label' => 'Verification', 'route' => 'internal.verification.index', 'pattern' => 'internal.verification.*'],
            ['label' => 'Sessions', 'route' => 'internal.sessions.index', 'pattern' => 'internal.sessions.*'],
            ['label' => 'Transactions', 'route' => 'internal.transactions.index', 'pattern' => 'internal.transactions.*'],
            ['label' => 'Attachments', 'route' => 'internal.attachments.index', 'pattern' => 'internal.attachments.*'],
            ['label' => 'Tenant Audit', 'route' => 'internal.audit.tenant-facing.index', 'pattern' => 'internal.audit.tenant-facing.*'],
            ['label' => 'Platform Audit', 'route' => 'internal.audit.platform.index', 'pattern' => 'internal.audit.platform.*'],
            ['label' => 'WAHA', 'route' => 'internal.waha.index', 'pattern' => 'internal.waha.*'],
        ];
    }
}
