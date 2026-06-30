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
            'icon_key' => $this->resolveIconKey(
                $categoryType,
                $tenantType,
                $payload['icon_key'] ?? null,
            ),
            'color_preset_key' => $this->resolveColorPresetKey(
                $categoryType,
                $tenantType,
                $payload['color_preset_key'] ?? null,
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
        $iconKey = $this->resolveIconKey(
            $categoryType,
            $category->tenant->tenant_type,
            $payload['icon_key'] ?? $category->icon_key,
        );
        $colorPresetKey = $this->resolveColorPresetKey(
            $categoryType,
            $category->tenant->tenant_type,
            $payload['color_preset_key'] ?? $category->color_preset_key,
        );

        if ($category->is_system) {
            $category->forceFill([
                'icon_key' => $iconKey,
                'color_preset_key' => $colorPresetKey,
            ])->save();

            return $category->fresh();
        }

        $category->fill([
            'type' => $categoryType->value,
            'name' => trim((string) $payload['name']),
            'icon_key' => $iconKey,
            'color_preset_key' => $colorPresetKey,
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

    private function resolveIconKey(CategoryType $categoryType, mixed $tenantType, mixed $iconKey): string
    {
        if (is_string($iconKey) && CategoryVisualCatalog::hasIconKey($iconKey)) {
            return $iconKey;
        }

        return CategoryVisualCatalog::defaultIconKeyForCustomCategory($tenantType, $categoryType);
    }

    private function resolveColorPresetKey(CategoryType $categoryType, mixed $tenantType, mixed $colorPresetKey): string
    {
        if (is_string($colorPresetKey) && CategoryVisualCatalog::hasColorPresetKey($colorPresetKey)) {
            return $colorPresetKey;
        }

        return CategoryVisualCatalog::defaultColorPresetKeyForCustomCategory($tenantType, $categoryType);
    }
}
