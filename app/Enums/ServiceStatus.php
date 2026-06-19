<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServiceStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case ENDED = 'ended';
}
