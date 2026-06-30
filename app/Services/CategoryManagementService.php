<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\TenantUser;
use App\Support\CategoryVisualCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(TenantUser $owner, array $payload): Category
    {
        $tenantType = $owner->tenant->tenant_type;
        $categoryType = CategoryType::from((string) $payload['type']);

        return Category::query()->create([
            'tenant_id' => $owner->tenant_id,
            'type' => $categoryType->value,
            'key' => $this->generateUniqueKey($owner->tenant_id, $categoryType->value, trim((string) $payload['name'])),
            'name' => trim((string) $payload['name']),
            'visual_preset_key' => $this->resolveVisualPresetKey(
                $categoryType,
                $tenantType,
                $payload['visual_preset_key'] ?? null,
            ),
            'keywords' => $this->parseKeywords($payload['keywords'] ?? null),
            'is_system' => false,
            'is_active' => $this->toBool($payload['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Category $category, array $payload): Category
    {
        $categoryType = CategoryType::from((string) $payload['type']);
        $visualPresetKey = $this->resolveVisualPresetKey(
            $categoryType,
            $category->tenant->tenant_type,
            $payload['visual_preset_key'] ?? $category->visual_preset_key,
        );

        if ($category->is_system) {
            $category->forceFill([
                'visual_preset_key' => $visualPresetKey,
            ])->save();

            return $category->fresh();
        }

        $category->fill([
            'type' => $categoryType->value,
            'name' => trim((string) $payload['name']),
            'visual_preset_key' => $visualPresetKey,
            'keywords' => $this->parseKeywords($payload['keywords'] ?? null),
            'is_active' => $this->toBool($payload['is_active'] ?? false),
        ])->save();

        return $category->fresh();
    }

    public function activate(Category $category): Category
    {
        $category->forceFill(['is_active' => true])->save();

        return $category->fresh();
    }

    public function deactivate(Category $category): Category
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'Kategori fallback wajib tetap aktif dan tidak bisa diarsipkan.',
            ]);
        }

        $category->forceFill(['is_active' => false])->save();

        return $category->fresh();
    }

    /**
     * @return array<int, string>|null
     */
    private function parseKeywords(mixed $keywords): ?array
    {
        if (! is_string($keywords) || trim($keywords) === '') {
            return null;
        }

        $items = preg_split('/[\r\n,]+/', $keywords) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $keyword = mb_strtolower(trim($item));

            if ($keyword === '' || in_array($keyword, $normalized, true)) {
                continue;
            }

            $normalized[] = $keyword;
        }

        return $normalized === [] ? null : $normalized;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function generateUniqueKey(int $tenantId, string $type, string $label): string
    {
        $baseKey = Str::of($label)
            ->lower()
            ->slug('_')
            ->value();

        if ($baseKey === '') {
            $baseKey = 'category';
        }

        $candidate = $baseKey;
        $suffix = 2;

        while (Category::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('key', $candidate)
            ->exists()
        ) {
            $candidate = $baseKey.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveVisualPresetKey(CategoryType $categoryType, mixed $tenantType, mixed $visualPresetKey): string
    {
        if (is_string($visualPresetKey) && CategoryVisualCatalog::hasPresetKey($visualPresetKey)) {
            return $visualPresetKey;
        }

        return CategoryVisualCatalog::defaultPresetKeyForCustomCategory($tenantType, $categoryType);
    }
}
