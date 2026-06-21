@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total akun</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">Semua akun tenant aktif dan inactive</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Akun aktif</p>
            <h2 class="dashboard-kpi-value">{{ $summary['active'] }}</h2>
            <p class="dashboard-kpi-note">Siap dipakai transaksi baru</p>
        </article>

        <article class="dashboard-kpi-card {{ $summary['inactive'] > 0 ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Akun inactive</p>
            <h2 class="dashboard-kpi-value">{{ $summary['inactive'] }}</h2>
            <p class="dashboard-kpi-note">Tidak dipakai parser transaksi</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Default account</p>
            <h2 class="dashboard-kpi-value dashboard-kpi-value-compact">{{ $summary['default_name'] }}</h2>
            <p class="dashboard-kpi-note">Dipakai saat user tidak menyebut akun</p>
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card members-form-card">
            <h2 class="dashboard-section-title">{{ $editingAccount ? 'Edit account' : 'Create account' }}</h2>
            <p class="panel-copy">Owner menyiapkan akun keuangan tenant. Satu akun aktif harus selalu menjadi default dan akun inactive tidak bisa dipakai untuk transaksi baru.</p>

            <form
                action="{{ $editingAccount ? route('tenant.accounts.update', $editingAccount['id']) : route('tenant.accounts.store') }}"
                method="post"
                class="field-grid"
            >
                @csrf
                @if ($editingAccount)
                    @method('put')
                @endif

                <x-ui.input
                    name="name"
                    label="Nama akun"
                    placeholder="Contoh: BCA Operasional"
                    :value="$editingAccount['name'] ?? null"
                    required
                />

                <label class="field" for="account_type">
                    <span class="field-label">Tipe akun</span>
                    <select id="account_type" name="account_type" class="input-control" required>
                        <option value="">Pilih tipe akun</option>
                        <option value="cash" @selected(old('account_type', $editingAccount['account_type'] ?? '') === 'cash')>Cash</option>
                        <option value="bank" @selected(old('account_type', $editingAccount['account_type'] ?? '') === 'bank')>Bank</option>
                        <option value="e_wallet" @selected(old('account_type', $editingAccount['account_type'] ?? '') === 'e_wallet')>E-Wallet</option>
                    </select>
                    @error('account_type')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </label>

                <x-ui.input
                    name="opening_balance"
                    label="Opening balance"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    :value="$editingAccount['opening_balance'] ?? '0.00'"
                    required
                />

                <div class="field">
                    <span class="field-label">Aturan akun</span>
                    <label class="checkbox-row">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingAccount['is_active'] ?? true))>
                        <span>Akun aktif dan bisa dipakai transaksi</span>
                    </label>

                    <label class="checkbox-row">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $editingAccount['is_default'] ?? false))>
                        <span>Jadikan akun default tenant</span>
                    </label>
                </div>

                <div class="field field-full">
                    <div class="members-form-actions">
                        <button class="button button-primary" type="submit">{{ $editingAccount ? 'Simpan perubahan' : 'Tambah akun' }}</button>

                        @if ($editingAccount)
                            <a href="{{ route('tenant.accounts.index') }}" class="button button-secondary">Batal edit</a>
                        @else
                            <a href="{{ route('tenant.dashboard') }}" class="button button-secondary">Kembali ke overview</a>
                        @endif
                    </div>
                </div>
            </form>
        </article>

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Rules</h2>

            <div class="dashboard-alert-list">
                <p>Tenant wajib memiliki minimal satu akun aktif.</p>
                <p>Satu akun aktif ditandai sebagai default account.</p>
                <p>Akun default dipakai saat transaksi masuk tanpa menyebut akun.</p>
                <p>Akun inactive tetap tersimpan, tetapi tidak dipakai untuk transaksi baru.</p>
            </div>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Akun</th>
                    <th>Tipe</th>
                    <th>Opening balance</th>
                    <th>Status</th>
                    <th>Last update</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>
                            <strong>{{ $account['name'] }}</strong>
                            @if ($account['is_default'])
                                <div class="members-cell-meta">Dipakai sebagai default account</div>
                            @endif
                        </td>
                        <td>{{ $account['account_type'] }}</td>
                        <td>Rp {{ $account['opening_balance'] }}</td>
                        <td>
                            <div class="members-badge-stack">
                                @if ($account['is_default'])
                                    <x-ui.badge tone="neutral">default</x-ui.badge>
                                @endif
                                <x-ui.badge tone="{{ $account['is_active'] ? 'success' : 'warning' }}">
                                    {{ $account['is_active'] ? 'active' : 'inactive' }}
                                </x-ui.badge>
                            </div>
                        </td>
                        <td>{{ $account['updated_at'] }}</td>
                        <td>
                            <div class="members-action-stack">
                                <a href="{{ route('tenant.accounts.index', ['edit' => $account['id']]) }}" class="button button-secondary button-compact">Edit</a>

                                @unless ($account['is_default'])
                                    <form action="{{ route('tenant.accounts.set-default', $account['id']) }}" method="post">
                                        @csrf
                                        <button class="button button-ghost button-compact" type="submit">Set default</button>
                                    </form>
                                @endunless

                                @if ($account['is_active'])
                                    <form action="{{ route('tenant.accounts.deactivate', $account['id']) }}" method="post">
                                        @csrf
                                        <button class="button button-ghost button-compact" type="submit">Nonaktifkan</button>
                                    </form>
                                @else
                                    <form action="{{ route('tenant.accounts.activate', $account['id']) }}" method="post">
                                        @csrf
                                        <button class="button button-secondary button-compact" type="submit">Aktifkan</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="dashboard-empty-cell">Belum ada akun untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
