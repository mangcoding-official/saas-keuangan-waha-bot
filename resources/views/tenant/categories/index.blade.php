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

    <section class="categories-summary-grid">
        <article class="categories-summary-card is-accent">
            <span class="categories-summary-icon is-total"></span>
            <p>Total Kategori</p>
            <h3>{{ str_pad((string) $summary['total'], 2, '0', STR_PAD_LEFT) }}</h3>
            <small>+{{ $summary['added_this_month'] }} bulan ini</small>
        </article>

        <article class="categories-summary-card">
            <span class="categories-summary-icon is-active"></span>
            <p>Kategori Aktif</p>
            <h3>{{ str_pad((string) $summary['active'], 2, '0', STR_PAD_LEFT) }}</h3>
            <small>{{ $summary['total'] > 0 ? round(($summary['active'] / $summary['total']) * 100) : 0 }}% dari total</small>
        </article>

        <article class="categories-summary-card">
            <span class="categories-summary-icon is-income"></span>
            <p>Tipe Pemasukan</p>
            <h3>{{ str_pad((string) $summary['income'], 2, '0', STR_PAD_LEFT) }}</h3>
            <small>{{ $summary['total'] > 0 ? round(($summary['income'] / $summary['total']) * 100) : 0 }}% dari total</small>
        </article>

        <article class="categories-summary-card">
            <span class="categories-summary-icon is-expense"></span>
            <p>Tipe Pengeluaran</p>
            <h3>{{ str_pad((string) $summary['expense'], 2, '0', STR_PAD_LEFT) }}</h3>
            <small>{{ $summary['total'] > 0 ? round(($summary['expense'] / $summary['total']) * 100) : 0 }}% dari total</small>
        </article>
    </section>

    <section class="categories-section-head">
        <div>
            <h2>Manajemen Kategori</h2>
            <p>Atur kategori transaksi untuk pelaporan keuangan yang lebih detail.</p>
        </div>
        <div class="categories-section-actions">
            @if ($activeSearch !== '')
                <a href="{{ route('tenant.categories.index') }}" class="categories-clear-search">Reset pencarian</a>
            @endif
            <a href="{{ route('tenant.categories.index', array_merge(request()->query(), ['create' => 1])) }}" class="categories-add-button">
                <span>+</span>
                <span>Tambah Kategori</span>
            </a>
        </div>
    </section>

    <section class="categories-table-card">
        <table class="categories-table">
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
                    <tr>
                        <td class="categories-name-cell">
                            <span class="categories-name-icon {{ $category['type_value'] === 'income' ? 'is-income' : 'is-expense' }}">
                                {{ $category['type_value'] === 'income' ? 'IN' : 'EX' }}
                            </span>
                            <div>
                                <strong>{{ $category['name'] }}</strong>
                                <span>ID: {{ $category['code'] }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="categories-type-pill {{ $category['type_value'] }}">
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
                            <span class="categories-status {{ $category['is_active'] ? 'is-active' : 'is-inactive' }}">
                                <span></span>
                                {{ $category['is_active'] ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="categories-action-cell">
                            @if ($category['is_system'])
                                <span class="categories-locked-action">Sistem</span>
                            @else
                                <details class="categories-action-menu" data-category-action-menu>
                                    <summary aria-label="Aksi kategori">...</summary>
                                    <div class="categories-action-popover">
                                        <a href="{{ route('tenant.categories.index', array_merge(request()->query(), ['edit' => $category['id']])) }}">Edit</a>
                                        @if ($category['is_active'])
                                            <form action="{{ route('tenant.categories.deactivate', $category['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Nonaktifkan</button>
                                            </form>
                                        @else
                                            <form action="{{ route('tenant.categories.activate', $category['id']) }}" method="post">
                                                @csrf
                                                <button type="submit">Aktifkan</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="categories-empty-cell">Belum ada kategori untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="categories-pagination">
            <p>Menampilkan {{ $categoryPaginator->firstItem() ?? 0 }}-{{ $categoryPaginator->lastItem() ?? 0 }} dari {{ $categoryPaginator->total() }} kategori</p>
            <div>
                @if ($categoryPaginator->onFirstPage())
                    <span class="categories-page disabled">&lt;</span>
                @else
                    <a href="{{ $categoryPaginator->previousPageUrl() }}" class="categories-page">&lt;</a>
                @endif
                @foreach ($visiblePages as $page)
                    <a href="{{ $categoryPaginator->url($page) }}" class="categories-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                @endforeach
                @if ($categoryPaginator->hasMorePages())
                    <a href="{{ $categoryPaginator->nextPageUrl() }}" class="categories-page">&gt;</a>
                @else
                    <span class="categories-page disabled">&gt;</span>
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
                    <a href="{{ route('tenant.categories.index', request()->except(['create', 'edit'])) }}">x</a>
                </header>

                @if (($editingCategory['is_system'] ?? false) === true)
                    <div class="categories-modal-alert">
                        Kategori sistem dikunci agar parser transaksi tetap konsisten.
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
