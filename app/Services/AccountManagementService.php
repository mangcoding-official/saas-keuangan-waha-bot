<?php

namespace App\Services;

use App\Models\Account;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountManagementService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(TenantUser $owner, array $payload): Account
    {
        return DB::transaction(function () use ($owner, $payload): Account {
            $activeAccountExists = Account::query()
                ->where('tenant_id', $owner->tenant_id)
                ->where('is_active', true)
                ->exists();

            $isActive = $this->toBool($payload['is_active'] ?? true);
            $isDefault = $this->toBool($payload['is_default'] ?? false) || ! $activeAccountExists;

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_default' => 'Akun default harus berstatus aktif.',
                ]);
            }

            if (! $activeAccountExists && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Tenant harus memiliki minimal satu akun aktif.',
                ]);
            }

            if ($isDefault) {
                $this->clearDefaultFlag($owner->tenant_id);
            }

            $account = Account::query()->create([
                'tenant_id' => $owner->tenant_id,
                'name' => trim((string) $payload['name']),
                'account_type' => (string) $payload['account_type'],
                'opening_balance' => round((float) $payload['opening_balance'], 2),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ]);

            if (! $activeAccountExists && ! $account->is_default) {
                $account->forceFill(['is_default' => true])->save();
            }

            return $account->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TenantUser $owner, Account $account, array $payload): Account
    {
        return DB::transaction(function () use ($owner, $account, $payload): Account {
            $isActive = $this->toBool($payload['is_active'] ?? false);
            $isDefault = $this->toBool($payload['is_default'] ?? false);

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_default' => 'Akun default harus berstatus aktif.',
                ]);
            }

            if ($account->is_default && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Pilih akun default lain dulu sebelum menonaktifkan akun ini.',
                ]);
            }

            if ($account->is_active && ! $isActive && $this->activeAccountCount($owner->tenant_id) <= 1) {
                throw ValidationException::withMessages([
                    'is_active' => 'Tenant harus memiliki minimal satu akun aktif.',
                ]);
            }

            if ($isDefault) {
                $this->clearDefaultFlag($owner->tenant_id);
            }

            $account->fill([
                'name' => trim((string) $payload['name']),
                'account_type' => (string) $payload['account_type'],
                'opening_balance' => round((float) $payload['opening_balance'], 2),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ])->save();

            if ($isActive && ! $this->hasActiveDefault($owner->tenant_id)) {
                $this->forceDefault($account);
            }

            return $account->fresh();
        });
    }

    public function setDefault(TenantUser $owner, Account $account): Account
    {
        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'account' => 'Akun inactive tidak bisa dijadikan default.',
            ]);
        }

        return DB::transaction(function () use ($owner, $account): Account {
            $this->clearDefaultFlag($owner->tenant_id);
            $this->forceDefault($account);

            return $account->fresh();
        });
    }

    public function activate(TenantUser $owner, Account $account): Account
    {
        return DB::transaction(function () use ($owner, $account): Account {
            $account->forceFill(['is_active' => true])->save();

            if (! $this->hasActiveDefault($owner->tenant_id)) {
                $this->forceDefault($account);
            }

            return $account->fresh();
        });
    }

    public function deactivate(TenantUser $owner, Account $account): Account
    {
        if ($account->is_default) {
            throw ValidationException::withMessages([
                'account' => 'Akun default tidak bisa dinonaktifkan. Pilih default lain terlebih dahulu.',
            ]);
        }

        if ($this->activeAccountCount($owner->tenant_id) <= 1) {
            throw ValidationException::withMessages([
                'account' => 'Tenant harus memiliki minimal satu akun aktif.',
            ]);
        }

        $account->forceFill(['is_active' => false])->save();

        return $account->fresh();
    }

    private function activeAccountCount(int $tenantId): int
    {
        return Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
    }

    private function hasActiveDefault(int $tenantId): bool
    {
        return Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
    }

    private function clearDefaultFlag(int $tenantId): void
    {
        Account::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => now()]);
    }

    private function forceDefault(Account $account): void
    {
        $account->forceFill([
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
