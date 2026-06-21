<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user('web')?->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('accounts', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'account_type' => ['required', Rule::in(AccountType::values())],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
