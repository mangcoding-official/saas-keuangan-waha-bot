<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\TenantUser;
use App\Services\CategoryManagementService;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryManagementService $categoryManagementService,
    ) {
    }

    public function index(): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $editingCategory = $this->resolveCategoryForEdit($owner);

        $categories = Category::query()
            ->where('tenant_id', $owner->tenant_id)
            ->orderByDesc('is_system')
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'type' => strtoupper($category->type->value),
                'name' => $category->name,
                'keywords' => $category->keywords ?? [],
                'is_system' => $category->is_system,
                'is_active' => $category->is_active,
                'updated_at' => Carbon::parse($category->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $activeCount = count(array_filter($categories, fn (array $category): bool => $category['is_active']));
        $systemCount = count(array_filter($categories, fn (array $category): bool => $category['is_system']));
        $incomeCount = count(array_filter($categories, fn (array $category): bool => $category['type'] === 'INCOME'));

        return view('tenant.categories.index', [
            'page' => [
                'title' => 'Categories',
                'description' => 'Owner mengelola kategori income dan expense berikut keyword parser tenant.',
                'eyebrow' => 'Tenant Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari kategori atau keyword',
                'search_placeholder' => 'Search category or keyword',
                'secondary_action' => [
                    'label' => 'Back to overview',
                    'href' => route('tenant.dashboard'),
                    'variant' => 'secondary',
                ],
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'editingCategory' => $editingCategory,
            'summary' => [
                'total' => count($categories),
                'active' => $activeCount,
                'system' => $systemCount,
                'income' => $incomeCount,
                'expense' => count($categories) - $incomeCount,
            ],
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $category = $this->categoryManagementService->create($owner, $request->validated());

        return to_route('tenant.categories.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Kategori berhasil dibuat',
            'message' => $category->name.' siap dipakai oleh parser transaksi tenant.',
        ]);
    }

    public function update(UpdateCategoryRequest $request, int $categoryId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = $request->user('web');
        $category = $this->findCategory($owner, $categoryId);
        $updated = $this->categoryManagementService->update($category, $request->validated());

        return to_route('tenant.categories.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Kategori diperbarui',
            'message' => $updated->name.' berhasil diperbarui.',
        ]);
    }

    public function activate(int $categoryId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $category = $this->findCategory($owner, $categoryId);
        $updated = $this->categoryManagementService->activate($category);

        return to_route('tenant.categories.index')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Kategori diaktifkan',
            'message' => $updated->name.' kembali tersedia untuk parser transaksi.',
        ]);
    }

    public function deactivate(int $categoryId): RedirectResponse
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $category = $this->findCategory($owner, $categoryId);
        $updated = $this->categoryManagementService->deactivate($category);

        return to_route('tenant.categories.index')->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Kategori dinonaktifkan',
            'message' => $updated->name.' tidak akan dipakai parser sampai diaktifkan kembali.',
        ]);
    }

    private function resolveCategoryForEdit(TenantUser $owner): ?array
    {
        $categoryId = request()->integer('edit');

        if ($categoryId <= 0) {
            return null;
        }

        $category = $this->findCategory($owner, $categoryId);

        return [
            'id' => $category->id,
            'type' => $category->type->value,
            'name' => $category->name,
            'keywords' => implode(', ', $category->keywords ?? []),
            'is_system' => $category->is_system,
            'is_active' => $category->is_active,
        ];
    }

    private function findCategory(TenantUser $owner, int $categoryId): Category
    {
        return Category::query()
            ->where('tenant_id', $owner->tenant_id)
            ->findOrFail($categoryId);
    }
}
