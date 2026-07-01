<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InviteStatus: string
{
    use HasValues;

    case PENDING = 'pending';
    case USED = 'used';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
