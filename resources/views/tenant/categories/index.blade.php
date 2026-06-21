@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total kategori</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">Income dan expense milik tenant</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Kategori aktif</p>
            <h2 class="dashboard-kpi-value">{{ $summary['active'] }}</h2>
            <p class="dashboard-kpi-note">Dipakai untuk parser transaksi</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Income vs expense</p>
            <h2 class="dashboard-kpi-value">{{ $summary['income'] }} / {{ $summary['expense'] }}</h2>
            <p class="dashboard-kpi-note">Distribusi kategori tenant</p>
        </article>

        <article class="dashboard-kpi-card {{ $summary['system'] > 0 ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Kategori sistem</p>
            <h2 class="dashboard-kpi-value">{{ $summary['system'] }}</h2>
            <p class="dashboard-kpi-note">Wajib ada untuk admin fee transfer</p>
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card members-form-card">
            <h2 class="dashboard-section-title">{{ $editingCategory ? 'Edit category' : 'Create category' }}</h2>
            <p class="panel-copy">Owner mengelola kategori dan keyword parser. Kata kunci dipisah dengan koma atau baris baru agar transaksi WhatsApp lebih mudah dikenali.</p>

            @if (($editingCategory['is_system'] ?? false) === true)
                <div class="members-alert members-alert-warning">
                    Kategori sistem dikunci dari dashboard owner agar parser admin fee tetap konsisten.
                </div>
            @endif

            <form
                action="{{ $editingCategory ? route('tenant.categories.update', $editingCategory['id']) : route('tenant.categories.store') }}"
                method="post"
                class="field-grid"
            >
                @csrf
                @if ($editingCategory)
                    @method('put')
                @endif

                <label class="field" for="type">
                    <span class="field-label">Tipe kategori</span>
                    <select
                        id="type"
                        name="type"
                        class="input-control"
                        @disabled(($editingCategory['is_system'] ?? false) === true)
                        required
                    >
                        <option value="">Pilih tipe kategori</option>
                        <option value="income" @selected(old('type', $editingCategory['type'] ?? '') === 'income')>Income</option>
                        <option value="expense" @selected(old('type', $editingCategory['type'] ?? '') === 'expense')>Expense</option>
                    </select>
                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="type" value="{{ old('type', $editingCategory['type']) }}">
                    @endif
                    @error('type')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </label>

                <x-ui.input
                    name="name"
                    label="Nama kategori"
                    placeholder="Contoh: Langganan Tools"
                    :value="$editingCategory['name'] ?? null"
                    :disabled="($editingCategory['is_system'] ?? false) === true"
                    required
                />

                <label class="field field-full" for="keywords">
                    <span class="field-label">Keyword / alias</span>
                    <textarea
                        id="keywords"
                        name="keywords"
                        class="input-control input-textarea"
                        placeholder="Contoh: langganan, tools, canva&#10;Pisahkan dengan koma atau enter"
                        @disabled(($editingCategory['is_system'] ?? false) === true)
                    >{{ old('keywords', $editingCategory['keywords'] ?? '') }}</textarea>
                    @error('keywords')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </label>

                <div class="field field-full">
                    <label class="checkbox-row">
                        <input type="hidden" name="is_active" value="0">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            @checked(old('is_active', $editingCategory['is_active'] ?? true))
                            @disabled(($editingCategory['is_system'] ?? false) === true)
                        >
                        <span>Kategori aktif dan boleh dipakai parser</span>
                    </label>

                    @if (($editingCategory['is_system'] ?? false) === true)
                        <input type="hidden" name="is_active" value="{{ ($editingCategory['is_active'] ?? true) ? 1 : 0 }}">
                    @endif
                </div>

                <div class="field field-full">
                    <div class="members-form-actions">
                        @if (($editingCategory['is_system'] ?? false) === true)
                            <a href="{{ route('tenant.categories.index') }}" class="button button-secondary">Kembali ke categories</a>
                        @else
                            <button class="button button-primary" type="submit">{{ $editingCategory ? 'Simpan perubahan' : 'Tambah kategori' }}</button>

                            @if ($editingCategory)
                                <a href="{{ route('tenant.categories.index') }}" class="button button-secondary">Batal edit</a>
                            @else
                                <a href="{{ route('tenant.dashboard') }}" class="button button-secondary">Kembali ke overview</a>
                            @endif
                        @endif
                    </div>
                </div>
            </form>
        </article>

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Rules</h2>

            <div class="dashboard-alert-list">
                <p>Kategori tenant dibagi menjadi income dan expense.</p>
                <p>Keyword atau alias dipakai parser untuk mencocokkan kategori dari pesan WhatsApp.</p>
                <p>Kategori inactive disimpan, tetapi tidak dipakai parser transaksi baru.</p>
                <p>Kategori sistem `Biaya Admin Transfer` wajib tetap tersedia.</p>
            </div>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Tipe</th>
                    <th>Keyword</th>
                    <th>Status</th>
                    <th>Last update</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <strong>{{ $category['name'] }}</strong>
                            @if ($category['is_system'])
                                <div class="members-cell-meta">Kategori sistem wajib</div>
                            @endif
                        </td>
                        <td>{{ $category['type'] }}</td>
                        <td>
                            @if ($category['keywords'] !== [])
                                <div class="resource-chip-group">
                                    @foreach ($category['keywords'] as $keyword)
                                        <span class="resource-chip">{{ $keyword }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="members-cell-meta">Belum ada keyword</span>
                            @endif
                        </td>
                        <td>
                            <div class="members-badge-stack">
                                @if ($category['is_system'])
                                    <x-ui.badge tone="neutral">system</x-ui.badge>
                                @endif
                                <x-ui.badge tone="{{ $category['is_active'] ? 'success' : 'warning' }}">
                                    {{ $category['is_active'] ? 'active' : 'inactive' }}
                                </x-ui.badge>
                            </div>
                        </td>
                        <td>{{ $category['updated_at'] }}</td>
                        <td>
                            <div class="members-action-stack">
                                @if (! $category['is_system'])
                                    <a href="{{ route('tenant.categories.index', ['edit' => $category['id']]) }}" class="button button-secondary button-compact">Edit</a>

                                    @if ($category['is_active'])
                                        <form action="{{ route('tenant.categories.deactivate', $category['id']) }}" method="post">
                                            @csrf
                                            <button class="button button-ghost button-compact" type="submit">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <form action="{{ route('tenant.categories.activate', $category['id']) }}" method="post">
                                            @csrf
                                            <button class="button button-secondary button-compact" type="submit">Aktifkan</button>
                                        </form>
                                    @endif
                                @else
                                    <span class="members-cell-meta">Action dibatasi</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="dashboard-empty-cell">Belum ada kategori untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
