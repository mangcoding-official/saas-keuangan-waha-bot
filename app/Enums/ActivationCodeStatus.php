<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ActivationCodeStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case USED = 'used';
    case EXPIRED = 'expired';
    case INVALIDATED = 'invalidated';
}
