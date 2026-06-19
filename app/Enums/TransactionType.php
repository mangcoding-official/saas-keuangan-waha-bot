<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TransactionType: string
{
    use HasValues;

    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';
}
