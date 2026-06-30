@extends('layouts.tenant')

@php
    $pageCount = max(1, (int) $categoryPaginator->lastPage());
    $currentPage = (int) $categoryPaginator->currentPage();
    $visiblePages = collect(range(max(1, $currentPage - 1), min($pageCount, $currentPage + 1)))->all();
    $isModalOpen = $editingCategory !== null || $isCreateModal;
    $selectedType = old('type', $editingCategory['type'] ?? 'expense');
    $selectedKeywords = old('keywords', $editingCategory['keywords'] ?? '');
    $selectedIconKey = old('icon_key', $editingCategory['icon_key'] ?? $defaultCreateIconKey);
    $selectedColorPresetKey = old('color_preset_key', $editingCategory['color_preset_key'] ?? $defaultCreateColorPresetKey);
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
                            <span class="accounts-name-icon categories-name-icon">
                                <span
                                    class="category-visual-badge category-visual-badge--sm"
                                    style="--category-bg: {{ $category['bg_color'] }}; --category-icon: {{ $category['icon_color'] }}; --category-mask: url('{{ $category['icon_mask_asset'] }}');"
                                    aria-hidden="true"
                                >
                                    <span class="category-visual-badge__glyph"></span>
                                </span>
                            </span>
                            <div>
                                <strong>{{ $category['name'] }}</strong>
                                <span>ID: {{ $category['code'] }} @if ($category['key']) · Key: {{ $category['key'] }} @endif</span>
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
                        Kategori fallback wajib tetap dikunci untuk nama, tipe, status, dan kata kunci. Icon dan warna masih bisa Anda ganti.
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

                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="name" value="{{ old('name', $editingCategory['name'] ?? '') }}">
                    @endif

                    <section class="categories-preset-picker">
                        <div class="categories-preset-head">
                            <div>
                                <span>Preview Kategori</span>
                                <p>Icon dan warna dipilih terpisah. User cukup memilih dari opsi sistem yang sudah tersedia.</p>
                            </div>
                            <div class="categories-preset-preview">
                                <span
                                    class="category-visual-badge category-visual-badge--preview"
                                    data-category-preview
                                    style="--category-bg: #eef2ff; --category-icon: #1e40af; --category-mask: none;"
                                    aria-hidden="true"
                                >
                                    <span class="category-visual-badge__glyph"></span>
                                </span>
                            </div>
                        </div>

                        <div class="categories-preset-groups">
                            <section class="categories-preset-group">
                                <div class="categories-preset-group-head">
                                    <strong>Pilih Icon</strong>
                                    <span>Hanya icon tenant {{ $iconGroups[0]['group_label'] ?? '' }} yang ditampilkan.</span>
                                </div>

                                @foreach ($iconGroups as $group)
                                    <div class="categories-icon-group">
                                        <div class="categories-preset-group-head">
                                            <strong>{{ $group['group_label'] }}</strong>
                                            <span>{{ $group['is_recommended'] ? 'Rekomendasi tenant ini' : 'Pilihan lain' }}</span>
                                        </div>
                                        <div class="categories-preset-grid">
                                            @foreach ($group['icons'] as $icon)
                                                <label class="categories-preset-option categories-icon-option">
                                                    <input
                                                        type="radio"
                                                        name="icon_key"
                                                        value="{{ $icon['key'] }}"
                                                        @checked($selectedIconKey === $icon['key'])
                                                        data-icon-option
                                                        data-icon-mask="{{ asset($icon['asset_path']) }}"
                                                        data-icon-label="{{ $icon['label'] }}"
                                                    >
                                                    <span class="categories-preset-card categories-icon-card">
                                                        <span
                                                            class="category-visual-badge category-visual-badge--sm"
                                                            style="--category-bg: #eef2ff; --category-icon: #1e40af; --category-mask: url('{{ asset($icon['asset_path']) }}');"
                                                            aria-hidden="true"
                                                        >
                                                            <span class="category-visual-badge__glyph"></span>
                                                        </span>
                                                        <small>{{ $icon['label'] }}</small>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </section>

                            <section class="categories-preset-group">
                                <div class="categories-preset-group-head">
                                    <strong>Pilih Warna</strong>
                                    <span>Warna berlaku global. Background soft dan warna icon akan mengikuti preset yang dipilih.</span>
                                </div>
                                <div class="categories-color-grid">
                                    @foreach ($colorPresets as $colorPreset)
                                        <label class="categories-preset-option categories-color-option">
                                            <input
                                                type="radio"
                                                name="color_preset_key"
                                                value="{{ $colorPreset['key'] }}"
                                                @checked($selectedColorPresetKey === $colorPreset['key'])
                                                data-color-option
                                                data-bg-color="{{ $colorPreset['bg_color'] }}"
                                                data-icon-color="{{ $colorPreset['icon_color'] }}"
                                                data-color-label="{{ $colorPreset['label'] }}"
                                            >
                                            <span class="categories-color-card">
                                                <span class="categories-color-swatch" style="--category-bg: {{ $colorPreset['bg_color'] }}; --category-icon: {{ $colorPreset['icon_color'] }};"></span>
                                                <small>{{ $colorPreset['label'] }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    </section>

                    <label>
                        <span>Kata Kunci</span>
                        <textarea
                            name="keywords"
                            placeholder="Contoh: sembako, supermarket, pasar"
                            @disabled(($editingCategory['is_system'] ?? false) === true)
                        >{{ $selectedKeywords }}</textarea>
                    </label>

                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="keywords" value="{{ $selectedKeywords }}">
                    @endif

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
                        <button type="submit" class="categories-modal-button primary">
                            {{ $editingCategory ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const actionMenus = Array.from(document.querySelectorAll('[data-category-action-menu]'));
            const iconInputs = Array.from(document.querySelectorAll('[data-icon-option]'));
            const colorInputs = Array.from(document.querySelectorAll('[data-color-option]'));
            const categoryPreview = document.querySelector('[data-category-preview]');

            if (categoryPreview && (iconInputs.length > 0 || colorInputs.length > 0)) {
                const syncPreview = function () {
                    const activeIcon = iconInputs.find((input) => input.checked);
                    const activeColor = colorInputs.find((input) => input.checked);

                    if (activeIcon) {
                        categoryPreview.style.setProperty('--category-mask', "url('" + activeIcon.dataset.iconMask + "')");
                    }

                    if (activeColor) {
                        categoryPreview.style.setProperty('--category-bg', activeColor.dataset.bgColor || '#eef2ff');
                        categoryPreview.style.setProperty('--category-icon', activeColor.dataset.iconColor || '#1e40af');
                    }
                };

                iconInputs.forEach(function (input) {
                    input.addEventListener('change', syncPreview);
                });

                colorInputs.forEach(function (input) {
                    input.addEventListener('change', syncPreview);
                });

                syncPreview();
            }

            if (actionMenus.length > 0) {
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
            }
        });
    </script>
@endsection
