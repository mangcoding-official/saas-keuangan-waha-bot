<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AiAddonStatus: string
{
    use HasValues;

    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
}
