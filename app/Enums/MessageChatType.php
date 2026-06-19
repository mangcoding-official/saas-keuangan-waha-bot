<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MessageChatType: string
{
    use HasValues;

    case PERSONAL = 'personal';
    case GROUP = 'group';
    case STATUS = 'status';
    case CHANNEL = 'channel';
    case BROADCAST = 'broadcast';
    case UNKNOWN = 'unknown';
}
