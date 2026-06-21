<?php

namespace App\Services;

use App\Enums\ActivationCodeStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ActivationCodeService
{
    public function issue(int $tenantUserId, string $invalidatedReason = 'regenerated'): string
    {
        DB::table('activation_codes')
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', ActivationCodeStatus::ACTIVE->value)
            ->update([
                'status' => ActivationCodeStatus::INVALIDATED->value,
                'active_lock' => null,
                'invalidated_at' => now(),
                'invalidated_reason' => $invalidatedReason,
                'updated_at' => now(),
            ]);

        $suffix = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
        $plainCode = 'KAS-'.$suffix;

        DB::table('activation_codes')->insert([
            'tenant_user_id' => $tenantUserId,
            'code_hash' => Hash::make($plainCode),
            'code_last4' => $suffix,
            'status' => ActivationCodeStatus::ACTIVE->value,
            'active_lock' => 1,
            'expires_at' => now()->addMinutes((int) config('platform.timeouts.activation_code_minutes')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plainCode;
    }
}
