<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ConversationIntentType: string
{
    use HasValues;

    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';
    case REPORT = 'report';
    case OTHER = 'other';
}
