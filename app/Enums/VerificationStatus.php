<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum VerificationStatus: string
{
    use HasValues;

    case PENDING_VERIFICATION = 'pending_verification';
    case VERIFIED = 'verified';
}
