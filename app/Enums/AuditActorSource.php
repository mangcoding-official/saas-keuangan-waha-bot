<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AuditActorSource: string
{
    use HasValues;

    case TENANT_USER = 'tenant_user';
    case SUPER_ADMIN = 'super_admin';
    case SYSTEM = 'system';
}
