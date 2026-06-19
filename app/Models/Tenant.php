<?php

namespace App\Models;

use App\Enums\AiAddonStatus;
use App\Enums\ServicePlan;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'tenant_type',
        'timezone',
        'tenant_status',
        'service_plan',
        'service_status',
        'ai_addon_status',
    ];

    protected function casts(): array
    {
        return [
            'tenant_type' => TenantType::class,
            'tenant_status' => TenantStatus::class,
            'service_plan' => ServicePlan::class,
            'service_status' => ServiceStatus::class,
            'ai_addon_status' => AiAddonStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }
}
