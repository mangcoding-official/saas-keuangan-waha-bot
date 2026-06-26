@extends('layouts.tenant')

@php
    $pageCount = max(1, (int) $transactionPaginator->lastPage());
    $currentPage = (int) $transactionPaginator->currentPage();
    $visiblePages = collect(range(max(1, $currentPage - 2), min($pageCount, $currentPage + 2)))->all();
    $isModalOpen = $editingTransaction !== null || $isCreateModal;
    $modalType = old('transaction_type', $editingTransaction['type_value'] ?? 'expense');
@endphp

@section('content')
    @if ($errors->any())
        <div class="tenant-inline-alert">{{ $errors->first() }}</div>
    @endif

    <section class="transactions-page-header">
        <div>
            <h2 class="transactions-page-title">Daftar Transaksi</h2>
            <p class="transactions-page-copy">Pantau seluruh arus kas keuangan Anda secara real-time.</p>
        </div>
        <div class="transactions-page-actions">
            <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['export' => 1])) }}" class="transactions-button secondary">Export</a>
            @if ($authUser->role->value === 'owner')
                <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['create' => 1])) }}" class="transactions-button primary">+ Tambah Transaksi</a>
            @endif
        </div>
    </section>

    <section class="transactions-summary-grid">
        <article class="transactions-summary-card">
            <p>Total Transaksi</p>
            <h3>{{ number_format($summary['total'], 0, ',', '.') }}</h3>
        </article>
        <article class="transactions-summary-card income">
            <p>Pemasukan</p>
            <h3>Rp {{ number_format($summary['income'], 0, ',', '.') }}</h3>
        </article>
        <article class="transactions-summary-card expense">
            <p>Pengeluaran</p>
            <h3>Rp {{ number_format($summary['expense'], 0, ',', '.') }}</h3>
        </article>
        <article class="transactions-summary-card">
            <p>Transfer</p>
            <h3>Rp {{ number_format($summary['transfer'], 0, ',', '.') }}</h3>
        </article>
    </section>

    <section class="transactions-filter-card">
        <h3>Filter Lanjutan</h3>
        <form method="get" class="transactions-filter-grid">
            <label>
                <span>Periode</span>
                <select name="period">
                    <option value="this_month" @selected($activeFilters['period'] === 'this_month')>Bulan Ini</option>
                    <option value="last_30_days" @selected($activeFilters['period'] === 'last_30_days')>30 Hari Terakhir</option>
                    <option value="all" @selected($activeFilters['period'] === 'all')>Semua Data</option>
                </select>
            </label>
            <label>
                <span>Tipe</span>
                <select name="type">
                    @foreach ($transactionTypes as $option)
                        <option value="{{ $option['value'] }}" @selected($activeFilters['type'] === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Kategori</span>
                <select name="category_id">
                    <option value="">Semua Kategori</option>
                    @foreach (array_merge($incomeCategories, $expenseCategories) as $category)
                        <option value="{{ $category['id'] }}" @selected($activeFilters['category_id'] === (string) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Akun</span>
                <select name="account_id">
                    <option value="">Semua Akun</option>
                    @foreach ($activeAccounts as $account)
                        <option value="{{ $account['id'] }}" @selected($activeFilters['account_id'] === (string) $account['id'])>{{ $account['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Pencatat</span>
                <select name="recorder_id">
                    <option value="">Semua Anggota</option>
                    @foreach ($recorders as $recorder)
                        <option value="{{ $recorder['id'] }}" @selected($activeFilters['recorder_id'] === (string) $recorder['id'])>{{ $recorder['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Pencarian</span>
                <input type="text" name="search" value="{{ $activeFilters['search'] }}" placeholder="Cari transaksi...">
            </label>
            <button type="submit" class="transactions-button primary">Terapkan</button>
        </form>
    </section>

    <section class="transactions-content-layout">
        <div class="transactions-main-pane">
            <section class="transactions-table-card">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>TANGGAL</th>
                            <th>DESKRIPSI</th>
                            <th>TIPE</th>
                            <th>KATEGORI</th>
                            <th>AKUN</th>
                            <th>PENCATAT</th>
                            <th>NOMINAL</th>
                            <th>STATUS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr class="{{ $selectedTransaction && $selectedTransaction['id'] === $transaction['id'] ? 'is-selected' : '' }}">
                                <td>{{ $transaction['date'] }}</td>
                                <td>{{ $transaction['description'] }}</td>
                                <td><span class="transactions-type-pill {{ $transaction['type_value'] }}">{{ ucfirst($transaction['type_value']) }}</span></td>
                                <td>{{ $transaction['category'] }}</td>
                                <td>{{ $transaction['source_account'] !== '-' ? $transaction['source_account'] : $transaction['destination_account'] }}</td>
                                <td>{{ $transaction['recorder'] }}</td>
                                <td class="{{ $transaction['type_value'] === 'expense' ? 'is-expense' : 'is-income' }}">{{ $transaction['amount'] }}</td>
                                <td><span class="transactions-status-pill">Berhasil</span></td>
                                <td>
                                    <div class="transactions-actions">
                                        <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['show' => $transaction['id']])) }}" class="transactions-link-button">Detail</a>
                                        @if ($authUser->role->value === 'owner')
                                            <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['show' => $transaction['id'], 'edit' => $transaction['id']])) }}" class="transactions-link-button">Edit</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="transactions-empty-cell">Belum ada transaksi pada filter yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="transactions-pagination">
                    <p>Menampilkan {{ $transactionPaginator->firstItem() ?? 0 }}-{{ $transactionPaginator->lastItem() ?? 0 }} dari {{ $transactionPaginator->total() }} transaksi</p>
                    <div>
                        @if ($transactionPaginator->onFirstPage())
                            <span class="transactions-page disabled">&lt;</span>
                        @else
                            <a href="{{ $transactionPaginator->previousPageUrl() }}" class="transactions-page">&lt;</a>
                        @endif
                        @foreach ($visiblePages as $page)
                            <a href="{{ $transactionPaginator->url($page) }}" class="transactions-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                        @endforeach
                        @if ($transactionPaginator->hasMorePages())
                            <a href="{{ $transactionPaginator->nextPageUrl() }}" class="transactions-page">&gt;</a>
                        @else
                            <span class="transactions-page disabled">&gt;</span>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </section>

    @if ($selectedTransaction && ! $isModalOpen)
        @php
            $detailTypeLabel = $selectedTransaction['type_value'] === 'income'
                ? 'Dana Masuk'
                : ($selectedTransaction['type_value'] === 'expense' ? 'Dana Keluar' : 'Transfer');
            $detailInitials = collect(explode(' ', $selectedTransaction['recorder']))
                ->filter()
                ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
                ->take(2)
                ->implode('');
            $showId = str_pad((string) $selectedTransaction['id'], 7, '0', STR_PAD_LEFT);
            $detailCloseQuery = request()->except(['show']);
            $detailSourceAccount = $selectedTransaction['source_account'];
            $detailDestinationAccount = $selectedTransaction['destination_account'];
        @endphp
        <div class="transactions-detail-overlay">
            <a class="transactions-detail-overlay-backdrop" href="{{ route('tenant.transactions.index', $detailCloseQuery) }}" aria-label="Tutup detail"></a>
            <aside class="transactions-detail-drawer">
                <header class="transactions-detail-head">
                    <div>
                        <h3>Detail Transaksi</h3>
                        <p>ID: TR-{{ $showId }}-MC</p>
                    </div>
                    <a href="{{ route('tenant.transactions.index', $detailCloseQuery) }}" aria-label="Tutup detail">×</a>
                </header>

                <div class="transactions-detail-body">
                    <section class="transactions-detail-highlight">
                        <div class="transactions-detail-highlight-head">
                            <span class="transactions-detail-check">✓</span>
                            <span class="transactions-status-pill">Berhasil</span>
                        </div>
                        <p>JUMLAH NOMINAL</p>
                        <h4>{{ $selectedTransaction['amount'] }}</h4>
                    </section>

                    <section class="transactions-detail-grid">
                        <article>
                            <small>Tanggal &amp; Waktu</small>
                            <strong>{{ $selectedTransaction['logged_at'] }} WIB</strong>
                        </article>
                        <article class="is-right">
                            <small>Tipe Transaksi</small>
                            <strong class="is-type">{{ $detailTypeLabel }}</strong>
                        </article>
                    </section>

                    <section class="transactions-detail-block">
                        <small>Kategori</small>
                        <strong>{{ $selectedTransaction['category'] }}</strong>
                    </section>

                    <section class="transactions-detail-block">
                        <small>Deskripsi</small>
                        <p>{{ $selectedTransaction['description'] }}</p>
                    </section>

                    <section class="transactions-detail-grid two-cols">
                        <article>
                            <small>Sumber Akun</small>
                            <strong>{{ $detailSourceAccount !== '-' ? $detailSourceAccount : '-' }}</strong>
                        </article>
                        <article>
                            <small>Tujuan Akun</small>
                            <strong>{{ $detailDestinationAccount !== '-' ? $detailDestinationAccount : '-' }}</strong>
                        </article>
                    </section>

                    <section class="transactions-detail-grid">
                        <article>
                            <small>Dicatat Oleh</small>
                            <div class="transactions-detail-recorder">
                                <span>{{ $detailInitials !== '' ? $detailInitials : 'SY' }}</span>
                                <strong>{{ $selectedTransaction['recorder'] }}</strong>
                            </div>
                        </article>
                        <article class="is-right">
                            <small>Verifikasi</small>
                            <strong class="is-verified">✔</strong>
                        </article>
                    </section>

                    <section class="transactions-detail-shortcuts">
                        <a href="{{ route('tenant.audit.index') }}">Lihat Log Audit</a>
                        <a href="javascript:void(0)" onclick="window.print()">Cetak Bukti Transaksi</a>
                    </section>
                </div>

                <footer class="transactions-detail-foot">

                </footer>
            </aside>
        </div>
    @endif

    @if ($isModalOpen)
        <div class="transactions-modal-backdrop">
            <div class="transactions-modal-card">
                <div class="transactions-modal-head">
                    <div>
                        <h3>{{ $editingTransaction ? 'Edit Transaksi' : 'Tambah Transaksi' }}</h3>
                        <p>{{ $editingTransaction ? 'ID: TRN-'.$editingTransaction['id'] : 'Input transaksi baru menggunakan format yang konsisten.' }}</p>
                    </div>
                    <a href="{{ route('tenant.transactions.index', request()->except(['edit', 'create'])) }}">✕</a>
                </div>

                @if ($editingTransaction)
                    <form action="{{ route('tenant.transactions.update', $editingTransaction['id']) }}" method="post" class="transactions-modal-form">
                        @csrf
                        @method('put')
                        <input type="hidden" name="transaction_type" value="{{ $modalType }}" data-transaction-type-input>
                        <div class="transactions-type-switch">
                            @foreach (['expense' => 'Pengeluaran', 'income' => 'Pemasukan', 'transfer' => 'Transfer'] as $typeValue => $typeLabel)
                                <button type="button" class="{{ $modalType === $typeValue ? 'active' : '' }}" data-type-option="{{ $typeValue }}">{{ $typeLabel }}</button>
                            @endforeach
                        </div>
                        <label><span>Nominal (IDR)</span><input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', $editingTransaction['amount_value']) }}" required></label>
                        <div class="transactions-modal-grid-2">
                            <label><span>Tanggal</span><input type="date" name="transaction_date" value="{{ old('transaction_date', $editingTransaction['transaction_date_value']) }}" required></label>
                            <label><span>Kategori</span>
                                <select name="category_id" data-category-select @disabled($modalType === 'transfer')>
                                    <option value="">Pilih kategori</option>
                                    @foreach ($incomeCategories as $category)
                                        <option value="{{ $category['id'] }}" data-category-type="income" @selected((string) old('category_id', $editingTransaction['category_id']) === (string) $category['id'])>{{ $category['name'] }}</option>
                                    @endforeach
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category['id'] }}" data-category-type="expense" @selected((string) old('category_id', $editingTransaction['category_id']) === (string) $category['id'])>{{ $category['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <div class="transactions-modal-grid-2">
                            <label><span>Akun Sumber</span>
                                <select name="source_account_id" data-source-account-select @disabled($modalType === 'income')>
                                    <option value="">Pilih akun</option>
                                    @foreach ($activeAccounts as $account)
                                        <option value="{{ $account['id'] }}" @selected((string) old('source_account_id', $editingTransaction['source_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span>Akun Tujuan</span>
                                <select name="destination_account_id" data-destination-account-select @disabled($modalType === 'expense')>
                                    <option value="">Pilih akun</option>
                                    @foreach ($activeAccounts as $account)
                                        <option value="{{ $account['id'] }}" @selected((string) old('destination_account_id', $editingTransaction['destination_account_id']) === (string) $account['id'])>{{ $account['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label><span>Deskripsi (Opsional)</span><textarea name="description">{{ old('description', $editingTransaction['description_value']) }}</textarea></label>
                        <div class="transactions-modal-note">Perubahan transaksi akan dicatat otomatis di <strong>Audit Log</strong>.</div>
                        <div class="transactions-modal-actions">
                            <a href="{{ route('tenant.transactions.index', request()->except(['edit', 'create'])) }}" class="transactions-button secondary">Batal</a>
                            <button type="submit" class="transactions-button primary">Simpan Perubahan</button>
                        </div>
                    </form>
                @else
                    <div class="transactions-modal-form">
                        <div class="transactions-modal-note">Untuk saat ini, pembuatan transaksi baru dilakukan melalui WhatsApp command agar sinkron dengan bot WAHA.</div>
                        <ul class="transactions-modal-help">
                            @foreach ($usageExamples as $example)
                                <li><code>{{ $example }}</code></li>
                            @endforeach
                        </ul>
                        <div class="transactions-modal-actions">
                            <a href="{{ route('tenant.transactions.index', request()->except(['create'])) }}" class="transactions-button primary">Mengerti</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($editingTransaction)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const typeInput = document.querySelector('[data-transaction-type-input]');
                const typeButtons = Array.from(document.querySelectorAll('[data-type-option]'));
                const sourceSelect = document.querySelector('[data-source-account-select]');
                const destinationSelect = document.querySelector('[data-destination-account-select]');
                const categorySelect = document.querySelector('[data-category-select]');

                if (!typeInput || typeButtons.length === 0 || !sourceSelect || !destinationSelect || !categorySelect) {
                    return;
                }

                const syncTypeState = (selectedType) => {
                    typeButtons.forEach((button) => {
                        button.classList.toggle('active', button.dataset.typeOption === selectedType);
                    });

                    sourceSelect.disabled = selectedType === 'income';
                    destinationSelect.disabled = selectedType === 'expense';
                    categorySelect.disabled = selectedType === 'transfer';

                    Array.from(categorySelect.options).forEach((option) => {
                        const optionType = option.dataset.categoryType;
                        if (!optionType) {
                            option.hidden = false;
                            return;
                        }

                        option.hidden = optionType !== selectedType;
                    });

                    const selectedOption = categorySelect.selectedOptions[0];
                    if (selectedOption && selectedOption.dataset.categoryType && selectedOption.dataset.categoryType !== selectedType) {
                        categorySelect.value = '';
                    }
                };

                typeButtons.forEach((button) => {
                    button.addEventListener('click', function () {
                        const nextType = this.dataset.typeOption;
                        typeInput.value = nextType;
                        syncTypeState(nextType);
                    });
                });

                syncTypeState(typeInput.value);
            });
        </script>
    @endif
@endsection
