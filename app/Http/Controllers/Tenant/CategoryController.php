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
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryManagementService $categoryManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var TenantUser $owner */
        $owner = auth('web')->user();
        $baseQuery = Category::query()
            ->where('tenant_id', $owner->tenant_id)
            ->orderByDesc('is_system')
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name');

        $allCategories = (clone $baseQuery)
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'type' => strtoupper($category->type->value),
                'type_value' => $category->type->value,
                'key' => $category->key,
                'name' => $category->name,
                'keywords' => $category->keywords ?? [],
                'is_system' => $category->is_system,
                'is_active' => $category->is_active,
                'code' => 'CAT-'.str_pad((string) $category->id, 3, '0', STR_PAD_LEFT),
                'updated_at' => Carbon::parse($category->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $activeCount = count(array_filter($allCategories, fn (array $category): bool => $category['is_active']));
        $systemCount = count(array_filter($allCategories, fn (array $category): bool => $category['is_system']));
        $incomeCount = count(array_filter($allCategories, fn (array $category): bool => $category['type'] === 'INCOME'));
        $addedThisMonth = Category::query()
            ->where('tenant_id', $owner->tenant_id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $filteredQuery = (clone $baseQuery);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $filteredQuery->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%')
                    ->orWhereJsonContains('keywords', mb_strtolower($search));
            });
        }

        $categoryPaginator = $filteredQuery
            ->paginate(4)
            ->withQueryString();

        $categories = $categoryPaginator->getCollection()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'type' => strtoupper($category->type->value),
                'type_value' => $category->type->value,
                'key' => $category->key,
                'name' => $category->name,
                'keywords' => $category->keywords ?? [],
                'is_system' => $category->is_system,
                'is_active' => $category->is_active,
                'code' => 'CAT-'.str_pad((string) $category->id, 3, '0', STR_PAD_LEFT),
                'updated_at' => Carbon::parse($category->updated_at)->timezone($owner->tenant->timezone)->format('d M Y H:i'),
            ])
            ->all();

        $editingCategory = $this->resolveCategoryForEdit($owner);

        return view('tenant.categories.index', [
            'page' => [
                'title' => null,
                'description' => null,
                'eyebrow' => '',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => 'Cari kategori...',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($owner),
            'authUser' => $owner,
            'editingCategory' => $editingCategory,
            'isCreateModal' => $request->boolean('create'),
            'summary' => [
                'total' => count($allCategories),
                'active' => $activeCount,
                'system' => $systemCount,
                'income' => $incomeCount,
                'expense' => count($allCategories) - $incomeCount,
                'added_this_month' => $addedThisMonth,
            ],
            'categories' => $categories,
            'categoryPaginator' => $categoryPaginator,
            'activeSearch' => $search,
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
            'title' => 'Kategori dipulihkan',
            'message' => $updated->name.' kembali aktif untuk transaksi dan parser.',
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
            'title' => 'Kategori diarsipkan',
            'message' => $updated->name.' disimpan untuk histori dan tidak dipakai transaksi baru.',
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
            'key' => $category->key,
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
