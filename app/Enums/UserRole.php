<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case OWNER = 'owner';
    case MEMBER = 'member';
}
