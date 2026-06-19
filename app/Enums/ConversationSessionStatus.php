<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ConversationSessionStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
