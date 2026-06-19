<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TenantStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
