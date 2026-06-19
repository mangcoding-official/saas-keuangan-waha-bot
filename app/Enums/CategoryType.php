<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CategoryType: string
{
    use HasValues;

    case INCOME = 'income';
    case EXPENSE = 'expense';
}
