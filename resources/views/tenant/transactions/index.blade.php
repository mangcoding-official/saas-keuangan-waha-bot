@extends('layouts.tenant')

@php
    $pageCount = max(1, (int) $transactionPaginator->lastPage());
    $currentPage = (int) $transactionPaginator->currentPage();
    $visiblePages = collect([1, 2, 3, $currentPage - 1, $currentPage, $currentPage + 1, $pageCount - 1, $pageCount])
        ->filter(fn (int $page): bool => $page >= 1 && $page <= $pageCount)
        ->unique()
        ->sort()
        ->values()
        ->all();
    $isModalOpen = $editingTransaction !== null || $isCreateModal;
    $modalType = old('transaction_type', $editingTransaction['type_value'] ?? 'expense');
@endphp

@section('content')
    @if ($errors->any())
        <div class="tenant-inline-alert">{{ $errors->first() }}</div>
    @endif

    <section class="transactions-overview-shell">
        <section class="transactions-page-header">
            <div>
                <h2 class="transactions-page-title">Daftar Transaksi</h2>
                <p class="transactions-page-copy">Pantau seluruh arus kas keuangan Anda secara real-time.</p>
            </div>
            <div class="transactions-page-actions">
                <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['export' => 1])) }}" class="transactions-button secondary">
                    <span class="transactions-button-icon" aria-hidden="true">
                        <img src="{{ asset('images/figma/accounts/table-download.svg') }}" alt="">
                    </span>
                    <span>Export</span>
                </a>
                @if ($authUser->role->value === 'owner')
                    <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['create' => 1])) }}" class="transactions-button primary">
                        <span class="transactions-button-icon" aria-hidden="true">
                            <img src="{{ asset('images/figma/accounts/plus.svg') }}" alt="">
                        </span>
                        <span>Tambah Transaksi</span>
                    </a>
                @endif
            </div>
        </section>

        <section class="transactions-summary-grid">
            @foreach ($summaryCards as $card)
                <article class="transactions-summary-card is-{{ $card['tone'] }}">
                    <div class="transactions-summary-card-head">
                        <span class="transactions-summary-icon" aria-hidden="true">
                            <img src="{{ $card['icon'] }}" alt="{{ $card['icon_alt'] }}">
                        </span>
                        <span class="transactions-summary-trend">{{ $card['change'] }}</span>
                    </div>
                    <p class="transactions-summary-label">{{ $card['label'] }}</p>
                    <h3 class="transactions-summary-value">{{ $card['value'] }}</h3>
                </article>
            @endforeach
        </section>

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
                                <td class="transactions-date-cell">
                                    <strong>{{ $transaction['date_short'] }} {{ $transaction['date_year'] }}</strong>
                                    {{-- <span>{{ $transaction['logged_time'] }}</span> --}}
                                </td>
                                <td class="transactions-description-cell">
                                    <strong>{{ $transaction['description'] }}</strong>
                                    <span>{{ $transaction['reference'] }}</span>
                                </td>
                                <td>
                                    <span class="transactions-type-pill {{ $transaction['type_value'] }}">
                                        {{ $transaction['type_value'] === 'income' ? 'Pemasukan' : ($transaction['type_value'] === 'expense' ? 'Pengeluaran' : 'Transfer') }}
                                    </span>
                                </td>
                                <td>{{ $transaction['category'] }}</td>
                                <td class="transactions-account-cell">
                                    @if ($transaction['type_value'] === 'transfer')
                                        <span>{{ $transaction['source_account'] }}</span>
                                        <span>{{ $transaction['destination_account'] }}</span>
                                    @else
                                        <span>{{ $transaction['source_account'] !== '-' ? $transaction['source_account'] : $transaction['destination_account'] }}</span>
                                    @endif
                                </td>
                                <td class="transactions-recorder-cell">
                                    <span class="transactions-recorder-avatar">{{ $transaction['recorder_initials'] }}</span>
                                    <span>{{ $transaction['recorder'] }}</span>
                                </td>
                                <td class="transactions-amount-cell {{ $transaction['type_value'] === 'expense' ? 'is-expense' : ($transaction['type_value'] === 'income' ? 'is-income' : 'is-transfer') }}">
                                    <strong>Rp. {{ number_format((float) $transaction['amount_value'], 0, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <span class="transactions-status-pill is-{{ $transaction['status_key'] }}">{{ strtoupper($transaction['status_label']) }}</span>
                                </td>
                                <td class="transactions-action-menu-cell">
                                    <details class="transactions-action-menu" data-transaction-action-menu>
                                        <summary aria-label="Aksi transaksi">
                                            <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                        </summary>
                                        <div class="transactions-action-popover">
                                            <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['show' => $transaction['id']])) }}">Detail</a>
                                            @if ($authUser->role->value === 'owner')
                                                <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['show' => $transaction['id'], 'edit' => $transaction['id']])) }}">Edit</a>
                                            @endif
                                        </div>
                                    </details>
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
                            <span class="transactions-page is-prev disabled" aria-hidden="true">
                                <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                            </span>
                        @else
                            <a href="{{ $transactionPaginator->previousPageUrl() }}" class="transactions-page is-prev" aria-label="Halaman sebelumnya">
                                <img src="{{ asset('images/figma/accounts/page-prev.svg') }}" alt="">
                            </a>
                        @endif
                        @foreach ($visiblePages as $page)
                            @if ($loop->index > 0 && $page - $visiblePages[$loop->index - 1] > 1)
                                <span class="transactions-page-gap">...</span>
                            @endif
                            <a href="{{ $transactionPaginator->url($page) }}" class="transactions-page {{ $page === $currentPage ? 'active' : '' }}">{{ $page }}</a>
                        @endforeach
                        @if ($transactionPaginator->hasMorePages())
                            <a href="{{ $transactionPaginator->nextPageUrl() }}" class="transactions-page is-next" aria-label="Halaman berikutnya">
                                <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                            </a>
                        @else
                            <span class="transactions-page is-next disabled" aria-hidden="true">
                                <img src="{{ asset('images/figma/accounts/page-next.svg') }}" alt="">
                            </span>
                        @endif
                    </div>
                </div>
        </section>
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
            $detailCloseUrl = route('tenant.transactions.index', $detailCloseQuery);
            $detailSourceAccount = $selectedTransaction['source_account'];
            $detailDestinationAccount = $selectedTransaction['destination_account'];
        @endphp
        <div class="transactions-detail-overlay" data-transactions-detail-overlay>
            <a class="transactions-detail-overlay-backdrop" href="{{ $detailCloseUrl }}" aria-label="Tutup detail"></a>
            <aside class="transactions-detail-drawer" role="dialog" aria-modal="true" aria-labelledby="transactions-detail-title" tabindex="-1">
                <header class="transactions-detail-head">
                    <div>
                        <h3 id="transactions-detail-title">Detail Transaksi</h3>
                        <p>ID: TR-{{ $showId }}-MC</p>
                    </div>
                    <a href="{{ $detailCloseUrl }}" aria-label="Tutup detail">&times;</a>
                </header>

                <div class="transactions-detail-body">
                    <section class="transactions-detail-highlight">
                        <div class="transactions-detail-highlight-head">
                            <span class="transactions-detail-check">&#10003;</span>
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
                            <strong class="is-verified">&#10003;</strong>
                        </article>
                    </section>

                    <section class="transactions-detail-shortcuts">
                        <a href="{{ route('tenant.audit.index') }}">Lihat Log Audit</a>
                        <a href="javascript:void(0)" onclick="window.print()">Cetak Bukti Transaksi</a>
                    </section>
                </div>

                <footer class="transactions-detail-foot">
                    <a href="{{ $detailCloseUrl }}" class="transactions-button secondary">Tutup</a>
                    @if ($authUser->role->value === 'owner')
                        <a href="{{ route('tenant.transactions.index', array_merge(request()->query(), ['edit' => $selectedTransaction['id']])) }}" class="transactions-button primary">Edit transaksi</a>
                    @endif
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
                    <a href="{{ route('tenant.transactions.index', request()->except(['edit', 'create'])) }}">&times;</a>
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
                const actionMenus = Array.from(document.querySelectorAll('[data-transaction-action-menu]'));
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

                document.addEventListener('click', function (event) {
                    actionMenus.forEach(function (menu) {
                        if (menu.open && !menu.contains(event.target)) {
                            menu.open = false;
                        }
                    });
                });
            });
        </script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const actionMenus = Array.from(document.querySelectorAll('[data-transaction-action-menu]'));

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
    @endif

    @if ($selectedTransaction && ! $isModalOpen)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const detailOverlay = document.querySelector('[data-transactions-detail-overlay]');
                const detailDrawer = detailOverlay?.querySelector('.transactions-detail-drawer');
                const closeUrl = @json($detailCloseUrl);

                if (!detailOverlay || !detailDrawer || !closeUrl) {
                    return;
                }

                document.body.classList.add('has-modal-open');
                detailDrawer.focus();

                const handleKeydown = (event) => {
                    if (event.key === 'Escape') {
                        window.location.href = closeUrl;
                    }
                };

                document.addEventListener('keydown', handleKeydown);

                window.addEventListener('beforeunload', function () {
                    document.body.classList.remove('has-modal-open');
                    document.removeEventListener('keydown', handleKeydown);
                }, { once: true });
            });
        </script>
    @endif
@endsection
