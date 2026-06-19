<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TenantType: string
{
    use HasValues;

    case PERSONAL = 'personal';
    case FAMILY = 'family';
    case UMKM = 'umkm';
    case TEAM = 'team';
    case COMPANY = 'company';
}
