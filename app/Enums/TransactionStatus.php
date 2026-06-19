<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TransactionStatus: string
{
    use HasValues;

    case COMPLETED = 'completed';
    case VOID = 'void';
}
