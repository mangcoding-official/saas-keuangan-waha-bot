@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total transaksi</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">Transaksi completed</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Income bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['income'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Pemasukan bulan berjalan</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Expense bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['expense'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Pengeluaran bulan berjalan</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Transfer bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['transfer'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Mutasi antar akun</p>
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card dashboard-table-card">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Nominal</th>
                        <th>Kategori</th>
                        <th>Akun</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction['date'] }}</td>
                            <td>{{ $transaction['type'] }}</td>
                            <td>{{ $transaction['amount'] }}</td>
                            <td>{{ $transaction['category'] }}</td>
                            <td>
                                @if ($transaction['type'] === 'TRANSFER')
                                    {{ $transaction['source_account'] }} -> {{ $transaction['destination_account'] }}
                                @else
                                    {{ $transaction['source_account'] !== '-' ? $transaction['source_account'] : $transaction['destination_account'] }}
                                @endif
                            </td>
                            <td>{{ $transaction['recorder'] }}</td>
                            <td>
                                <div class="members-action-stack">
                                    <a href="{{ route('tenant.transactions.index', ['show' => $transaction['id']]) }}" class="button button-secondary button-compact">Detail</a>
                                    @if ($authUser->role->value === 'owner')
                                        <a href="{{ route('tenant.transactions.index', ['show' => $transaction['id'], 'edit' => $transaction['id']]) }}" class="button button-ghost button-compact">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="dashboard-empty-cell">Belum ada transaksi yang tersimpan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <div class="dashboard-side-column">
            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Detail transaksi</h2>

                @if ($selectedTransaction)
                    <div class="dashboard-alert-list">
                        <p><strong>Tanggal:</strong> {{ $selectedTransaction['date'] }}</p>
                        <p><strong>Tipe:</strong> {{ $selectedTransaction['type'] }}</p>
                        <p><strong>Nominal:</strong> {{ $selectedTransaction['amount'] }}</p>
                        <p><strong>Kategori:</strong> {{ $selectedTransaction['category'] }}</p>
                        <p><strong>Sumber:</strong> {{ $selectedTransaction['source_account'] }}</p>
                        <p><strong>Tujuan:</strong> {{ $selectedTransaction['destination_account'] }}</p>
                        <p><strong>User:</strong> {{ $selectedTransaction['recorder'] }}</p>
                        <p><strong>Logged at:</strong> {{ $selectedTransaction['logged_at'] }}</p>
                        <p><strong>Deskripsi:</strong> {{ $selectedTransaction['description'] }}</p>
                    </div>

                    <div class="transaction-attachments">
                        <h3 class="dashboard-section-title">Lampiran</h3>

                        @if ($selectedTransaction['attachments'] !== [])
                            <div class="transaction-attachment-grid">
                                @foreach ($selectedTransaction['attachments'] as $attachment)
                                    <a
                                        href="{{ $attachment['url'] }}"
                                        class="transaction-attachment-card"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            src="{{ $attachment['url'] }}"
                                            alt="{{ $attachment['name'] }}"
                                            class="transaction-attachment-image"
                                            loading="lazy"
                                        >
                                        <span class="transaction-attachment-meta">
                                            <strong>{{ $attachment['name'] }}</strong>
                                            <small>
                                                {{ $attachment['size'] }}
                                                @if ($attachment['dimensions'])
                                                    · {{ $attachment['dimensions'] }}
                                                @endif
                                            </small>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="dashboard-kpi-note">Tidak ada lampiran pada transaksi ini.</p>
                        @endif
                    </div>

                    @if ($authUser->role->value === 'owner' && $editingTransaction)
                        <hr class="dashboard-divider">

                        <h3 class="dashboard-section-title">Edit transaksi</h3>

                        <form
                            action="{{ route('tenant.transactions.update', $editingTransaction['id']) }}"
                            method="post"
                            class="field-grid"
                        >
                            @csrf
                            @method('put')

                            <x-ui.input
                                name="amount"
                                label="Nominal"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :value="old('amount', $editingTransaction['amount_value'])"
                                required
                            />

                            <x-ui.input
                                name="transaction_date"
                                label="Tanggal transaksi"
                                type="date"
                                :value="old('transaction_date', $editingTransaction['transaction_date_value'])"
                                required
                            />

                            @if ($editingTransaction['type_value'] === 'income')
                                <label class="field" for="category_id">
                                    <span class="field-label">Kategori income</span>
                                    <select id="category_id" name="category_id" class="input-control" required>
                                        <option value="">Pilih kategori</option>
                                        @foreach ($incomeCategories as $category)
                                            <option value="{{ $category['id'] }}" @selected((string) old('category_id', $editingTransaction['category_id']) === (string) $category['id'])>{{ $category['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field" for="destination_account_id">
                                    <span class="field-label">Akun tujuan</span>
                                    <select id="destination_account_id" name="destination_account_id" class="input-control" required>
                                        <option value="">Pilih akun</option>
                                        @foreach ($activeAccounts as $account)
                                            <option value="{{ $account['id'] }}" @selected((string) old('destination_account_id', $editingTransaction['destination_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif ($editingTransaction['type_value'] === 'expense')
                                <label class="field" for="category_id">
                                    <span class="field-label">Kategori expense</span>
                                    <select id="category_id" name="category_id" class="input-control" required>
                                        <option value="">Pilih kategori</option>
                                        @foreach ($expenseCategories as $category)
                                            <option value="{{ $category['id'] }}" @selected((string) old('category_id', $editingTransaction['category_id']) === (string) $category['id'])>{{ $category['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field" for="source_account_id">
                                    <span class="field-label">Akun sumber</span>
                                    <select id="source_account_id" name="source_account_id" class="input-control" required>
                                        <option value="">Pilih akun</option>
                                        @foreach ($activeAccounts as $account)
                                            <option value="{{ $account['id'] }}" @selected((string) old('source_account_id', $editingTransaction['source_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                <label class="field" for="source_account_id">
                                    <span class="field-label">Akun sumber</span>
                                    <select id="source_account_id" name="source_account_id" class="input-control" required>
                                        <option value="">Pilih akun</option>
                                        @foreach ($activeAccounts as $account)
                                            <option value="{{ $account['id'] }}" @selected((string) old('source_account_id', $editingTransaction['source_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field" for="destination_account_id">
                                    <span class="field-label">Akun tujuan</span>
                                    <select id="destination_account_id" name="destination_account_id" class="input-control" required>
                                        <option value="">Pilih akun</option>
                                        @foreach ($activeAccounts as $account)
                                            <option value="{{ $account['id'] }}" @selected((string) old('destination_account_id', $editingTransaction['destination_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            <x-ui.input
                                name="description"
                                label="Deskripsi"
                                placeholder="Opsional"
                                :value="old('description', $editingTransaction['description_value'])"
                            />

                            <div class="field field-full">
                                <div class="members-form-actions">
                                    <button class="button button-primary" type="submit">Simpan perubahan</button>
                                    <a href="{{ route('tenant.transactions.index', ['show' => $selectedTransaction['id']]) }}" class="button button-secondary">Batal edit</a>
                                </div>
                            </div>
                        </form>
                    @elseif ($authUser->role->value === 'owner')
                        <hr class="dashboard-divider">

                        <div class="members-form-actions">
                            <a href="{{ route('tenant.transactions.index', ['show' => $selectedTransaction['id'], 'edit' => $selectedTransaction['id']]) }}" class="button button-primary">Edit transaksi</a>
                            <a href="{{ route('tenant.audit.index') }}" class="button button-secondary">Buka audit log</a>
                        </div>

                        <form
                            action="{{ route('tenant.transactions.void', $selectedTransaction['id']) }}"
                            method="post"
                            class="field-grid"
                            onsubmit="return confirm('Void transaksi ini? Tindakan ini akan menghapusnya dari daftar completed.');"
                        >
                            @csrf

                            <x-ui.input
                                name="void_reason"
                                label="Alasan void"
                                placeholder="Contoh: salah nominal atau duplikat input"
                                :value="old('void_reason')"
                                required
                            />

                            <div class="field field-full">
                                <button class="button button-ghost" type="submit">Void transaksi</button>
                            </div>
                        </form>
                    @endif
                @else
                    <x-ui.state-shell
                        title="Pilih transaksi"
                        description="Klik tombol detail pada tabel untuk melihat detail transaksi."
                        tone="neutral"
                    />
                @endif
            </article>
        </div>
    </section>
@endsection
