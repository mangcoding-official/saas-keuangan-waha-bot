<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'account_type',
        'is_default',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'is_default' => 'boolean',
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
