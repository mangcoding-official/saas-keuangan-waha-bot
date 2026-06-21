<?php

namespace App\Http\Requests;

use App\Enums\TenantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterTenantOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:150'],
            'tenant_type' => ['required', Rule::enum(TenantType::class)],
            'timezone' => ['required', 'string', Rule::in(config('platform.supported_timezones'))],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_whatsapp' => ['required', 'string', 'min:8', 'max:32'],
            'owner_email' => ['required', 'email', 'max:190', Rule::unique('tenant_users', 'email')],
            'owner_password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_name' => 'nama tenant',
            'tenant_type' => 'jenis tenant',
            'timezone' => 'timezone tenant',
            'owner_name' => 'nama owner',
            'owner_whatsapp' => 'nomor WhatsApp owner',
            'owner_email' => 'email owner',
            'owner_password' => 'password owner',
        ];
    }
}
