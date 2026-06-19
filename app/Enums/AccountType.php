<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AccountType: string
{
    use HasValues;

    case CASH = 'cash';
    case BANK = 'bank';
    case E_WALLET = 'e_wallet';
}
