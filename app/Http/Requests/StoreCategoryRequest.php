<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Support\CategoryVisualCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $tenantType = $this->user('web')?->tenant?->tenant_type;
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::in(CategoryType::values())],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('type', $type)),
            ],
            'icon_key' => ['required', 'string', Rule::in(
                $tenantType ? CategoryVisualCatalog::allowedIconKeysForTenantType($tenantType) : CategoryVisualCatalog::iconKeys()
            )],
            'color_preset_key' => ['required', 'string', Rule::in(CategoryVisualCatalog::colorPresetKeys())],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
