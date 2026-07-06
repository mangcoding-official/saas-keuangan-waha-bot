<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PasswordResetTokenStatus: string
{
    use HasValues;

    case PENDING = 'pending';
    case SENT = 'sent';
    case USED = 'used';
    case EXPIRED = 'expired';
    case INVALIDATED = 'invalidated';
    case DELIVERY_FAILED = 'delivery_failed';
    case REJECTED = 'rejected';
    case RATE_LIMITED = 'rate_limited';
}
