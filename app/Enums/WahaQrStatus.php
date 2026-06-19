<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WahaQrStatus: string
{
    use HasValues;

    case NOT_REQUIRED = 'not_required';
    case READY = 'ready';
    case EXPIRED = 'expired';
    case UNKNOWN = 'unknown';
}
