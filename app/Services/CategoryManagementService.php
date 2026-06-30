<?php

namespace App\Services;

use App\Models\Category;
use App\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(TenantUser $owner, array $payload): Category
    {
        return Category::query()->create([
            'tenant_id' => $owner->tenant_id,
            'type' => (string) $payload['type'],
            'key' => $this->generateUniqueKey($owner->tenant_id, (string) $payload['type'], trim((string) $payload['name'])),
            'name' => trim((string) $payload['name']),
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
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'Kategori fallback wajib tidak bisa diubah dari dashboard owner.',
            ]);
        }

        $category->fill([
            'type' => (string) $payload['type'],
            'name' => trim((string) $payload['name']),
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
}
