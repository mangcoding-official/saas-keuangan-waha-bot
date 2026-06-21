<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'keywords',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'keywords' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
