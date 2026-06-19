<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServicePlan: string
{
    use HasValues;

    case ALPHA = 'alpha';
}
