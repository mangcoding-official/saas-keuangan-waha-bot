@extends('layouts.tenant')

@php
    $pageCount = max(1, (int) $categoryPaginator->lastPage());
    $currentPage = (int) $categoryPaginator->currentPage();
    $visiblePages = collect(range(max(1, $currentPage - 1), min($pageCount, $currentPage + 1)))->all();
    $isModalOpen = $editingCategory !== null || $isCreateModal;
    $selectedType = old('type', $editingCategory['type'] ?? 'expense');
    $selectedKeywords = old('keywords', $editingCategory['keywords'] ?? '');
@endphp

@section('content')
    @if ($errors->any())
        <div class="tenant-inline-alert">{{ $errors->first() }}</div>
    @endif

    <section class="accounts-page-header categories-page-header">
        <div>
            <h2 class="accounts-page-title">Manajemen Kategori</h2>
            <p class="accounts-page-copy">Atur kategori transaksi untuk pelaporan keuangan yang lebih detail.</p>
        </div>
        <div class="accounts-page-actions">
            <a href="{{ route('tenant.categories.index', array_merge(request()->query(), ['create' => 1])) }}" class="accounts-add-button">
                <span class="accounts-add-button-icon" aria-hidden="true">
                    <img src="{{ asset('images/figma/accounts/plus.svg') }}" alt="">
                </span>
                <span>Tambah Kategori</span>
            </a>
        </div>
    </section>

    <section class="categories-summary-grid">
        <article class="categories-summary-card is-total">
            <div class="categories-summary-icon is-total" aria-hidden="true">
                <span class="categories-summary-glyph is-total">
                    <img src="{{ asset('images/Icon-category.png') }}" alt="">
                </span>
            </div>
            <p class="categories-summary-label">Total Kategori</p>
            <h2 class="categories-summary-value">{{ str_pad((string) $summary['total'], 2, '0', STR_PAD_LEFT) }}</h2>
        </article>

        <article class="categories-summary-card is-active">
            <div class="categories-summary-icon is-active" aria-hidden="true">
                <span class="categories-summary-glyph is-active">
                    <img src="{{ asset('images/Icon-checklist.png') }}" alt="">
                </span>
            </div>
            <p class="categories-summary-label">Kategori Aktif</p>
            <h2 class="categories-summary-value">{{ str_pad((string) $summary['active'], 2, '0', STR_PAD_LEFT) }}</h2>
        </article>

        <article class="categories-summary-card is-income">
            <div class="categories-summary-icon is-income" aria-hidden="true">
                <span class="categories-summary-glyph is-income">
                    <img src="{{ asset('images/Icon-income.png') }}" alt="">
                </span>
            </div>
            <p class="categories-summary-label">Tipe Pemasukan</p>
            <h2 class="categories-summary-value">{{ str_pad((string) $summary['income'], 2, '0', STR_PAD_LEFT) }}</h2>
        </article>

        <article class="categories-summary-card is-expense">
            <div class="categories-summary-icon is-expense" aria-hidden="true">
                <span class="categories-summary-glyph is-expense">
                    <img src="{{ asset('images/Icon-expanse.png') }}" alt="">
                </span>
            </div>
            <p class="categories-summary-label">Tipe Pengeluaran</p>
            <h2 class="categories-summary-value">{{ str_pad((string) $summary['expense'], 2, '0', STR_PAD_LEFT) }}</h2>
        </article>
    </section>

    <section class="accounts-table-card categories-table-card">
        <div class="accounts-table-head">
            <h3>Data Kategori</h3>
            <div class="accounts-table-tools">
                <a
                    href="{{ $activeSearch !== '' ? route('tenant.categories.index', request()->except(['search'])) : route('tenant.categories.index', request()->query()) }}"
                    class="accounts-table-tool"
                    aria-label="{{ $activeSearch !== '' ? 'Reset pencarian' : 'Urutkan data' }}"
                    title="{{ $activeSearch !== '' ? 'Reset pencarian' : 'Urutkan data' }}"
                >
                    <img src="{{ asset('images/figma/accounts/table-list.svg') }}" alt="">
                </a>
            </div>
        </div>

        <table class="accounts-table categories-table">
            <thead>
                <tr>
                    <th>NAMA KATEGORI</th>
                    <th>TIPE</th>
                    <th>KATA KUNCI</th>
                    <th>STATUS</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="{{ $category['is_active'] ? '' : 'is-inactive-row' }}">
                        <td class="accounts-name-cell categories-name-cell">
                            <span class="accounts-name-icon categories-name-icon {{ $category['type_value'] === 'income' ? 'is-income' : 'is-expense' }}">
                                <span class="categories-name-glyph {{ $category['type_value'] === 'income' ? 'is-income' : 'is-expense' }}"></span>
                            </span>
                            <div>
                                <strong>{{ $category['name'] }}</strong>
                                <span>ID: {{ $category['code'] }} @if($category['key']) · Key: {{ $category['key'] }} @endif</span>
                            </div>
                        </td>
                        <td>
                            <span class="accounts-type-pill categories-type-pill {{ $category['type_value'] }}">
                                {{ $category['type_value'] === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                            </span>
                        </td>
                        <td>
                            @if ($category['keywords'] !== [])
                                <div class="categories-keyword-group">
                                    @foreach ($category['keywords'] as $keyword)
                                        <span class="categories-keyword-chip">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="categories-empty-keywords">Belum ada kata kunci</span>
                            @endif
                        </td>
                        <td>
                            <span class="accounts-status-inline {{ $category['is_active'] ? 'is-active' : 'is-inactive' }}">
                                <span></span>
                                {{ $category['is_active'] ? 'Aktif' : 'Archived' }}
                            </span>
                        </td>
                        <td class="accounts-action-menu-cell">
                            @if ($category['is_system'])
                                <span class="categories-locked-action">Wajib</span>
                            @else
                                <details class="accounts-action-menu" data-category-action-menu>
                                    <summary aria-label="Aksi kategori">
                                        <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                    </summary>
                                    <div class="accounts-action-popover">
                                        <a href="{{ route('tenant.categories.index', array_merge(request()->query(), ['edit' => $category['id']])) }}">Edit</a>
                                        @if ($category['is_active'])
                                            <form action="{{ route('tenant.categories.deactivate', $category['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Arsipkan</button>
                                            </form>
                                        @else
                                            <form action="{{ route('tenant.categories.activate', $category['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Pulihkan</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="accounts-empty-cell">Belum ada kategori untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="accounts-pagination">
            <p>Menampilkan {{ $categoryPaginator->firstItem() ?? 0 }}-{{ $categoryPaginator->lastItem() ?? 0 }} dari {{ $categoryPaginator->total() }} kategori</p>
            <div>
                @if ($categoryPaginator->onFirstPage())
                    <span class="accounts-page is-prev disabled" aria-hidden="true">
                        <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                    </span>
                @else
                    <a href="{{ $categoryPaginator->previousPageUrl() }}" class="accounts-page is-prev" aria-label="Halaman sebelumnya">
                        <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                    </a>
                @endif
                @foreach ($visiblePages as $page)
                    <a href="{{ $categoryPaginator->url($page) }}" class="accounts-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                @endforeach
                @if ($categoryPaginator->hasMorePages())
                    <a href="{{ $categoryPaginator->nextPageUrl() }}" class="accounts-page is-next" aria-label="Halaman berikutnya">
                        <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                    </a>
                @else
                    <span class="accounts-page is-next disabled" aria-hidden="true">
                        <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                    </span>
                @endif
            </div>
        </div>
    </section>

    @if ($isModalOpen)
        <div class="categories-modal-backdrop">
            <div class="categories-modal-card">
                <header class="categories-modal-head">
                    <div>
                        <h3>{{ $editingCategory ? 'Edit Kategori' : 'Tambah Kategori' }}</h3>
                        <p>{{ $editingCategory ? 'Perbarui kategori transaksi yang sudah ada.' : 'Buat kategori baru agar pencatatan lebih rapi.' }}</p>
                    </div>
                    <a
                        href="{{ route('tenant.categories.index', request()->except(['create', 'edit'])) }}"
                        class="categories-modal-close"
                        aria-label="Tutup modal"
                    >
                        <span aria-hidden="true">&times;</span>
                    </a>
                </header>

                @if (($editingCategory['is_system'] ?? false) === true)
                    <div class="categories-modal-alert">
                        Kategori fallback wajib dikunci agar parser transaksi tetap konsisten.
                    </div>
                @endif

                <form
                    action="{{ $editingCategory ? route('tenant.categories.update', $editingCategory['id']) : route('tenant.categories.store') }}"
                    method="post"
                    class="categories-modal-form"
                >
                    @csrf
                    @if ($editingCategory)
                        @method('put')
                    @endif

                    <div class="categories-type-switch">
                        <label>
                            <input type="radio" name="type" value="income" @checked($selectedType === 'income') @disabled(($editingCategory['is_system'] ?? false) === true)>
                            <span>Pemasukan</span>
                        </label>
                        <label>
                            <input type="radio" name="type" value="expense" @checked($selectedType === 'expense') @disabled(($editingCategory['is_system'] ?? false) === true)>
                            <span>Pengeluaran</span>
                        </label>
                    </div>

                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="type" value="{{ $selectedType }}">
                    @endif

                    <label>
                        <span>Nama Kategori</span>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $editingCategory['name'] ?? '') }}"
                            placeholder="Contoh: Belanja Bulanan"
                            @disabled(($editingCategory['is_system'] ?? false) === true)
                            required
                        >
                    </label>

                    <label>
                        <span>Kata Kunci</span>
                        <textarea
                            name="keywords"
                            placeholder="Contoh: sembako, supermarket, pasar"
                            @disabled(($editingCategory['is_system'] ?? false) === true)
                        >{{ $selectedKeywords }}</textarea>
                    </label>

                    <label class="categories-toggle-row">
                        <input type="hidden" name="is_active" value="0">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            @checked(old('is_active', $editingCategory['is_active'] ?? true))
                            @disabled(($editingCategory['is_system'] ?? false) === true)
                        >
                            <span>Aktifkan kategori ini</span>
                        </label>

                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="is_active" value="{{ ($editingCategory['is_active'] ?? true) ? 1 : 0 }}">
                    @endif

                    <footer class="categories-modal-actions">
                        <a href="{{ route('tenant.categories.index', request()->except(['create', 'edit'])) }}" class="categories-modal-button secondary">Batal</a>
                        @if (($editingCategory['is_system'] ?? false) !== true)
                            <button type="submit" class="categories-modal-button primary">
                                {{ $editingCategory ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                            </button>
                        @endif
                    </footer>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const actionMenus = Array.from(document.querySelectorAll('[data-category-action-menu]'));

            if (actionMenus.length === 0) {
                return;
            }

            document.addEventListener('click', function (event) {
                actionMenus.forEach(function (menu) {
                    if (menu.open && !menu.contains(event.target)) {
                        menu.open = false;
                    }
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }

                actionMenus.forEach(function (menu) {
                    menu.open = false;
                });
            });
        });
    </script>
@endsection
