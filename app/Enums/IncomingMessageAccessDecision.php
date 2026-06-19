<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IncomingMessageAccessDecision: string
{
    use HasValues;

    case ACCEPTED = 'accepted';
    case IGNORED = 'ignored';
}
