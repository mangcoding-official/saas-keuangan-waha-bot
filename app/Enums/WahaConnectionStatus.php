<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WahaConnectionStatus: string
{
    use HasValues;

    case CONNECTED = 'connected';
    case CONNECTING = 'connecting';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
}
