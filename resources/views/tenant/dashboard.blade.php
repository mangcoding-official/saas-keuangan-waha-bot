@extends('layouts.tenant')

@section('content')
    <section class="tenant-dashboard-frame">
        <section class="tenant-dashboard-kpi-grid">
            @foreach ($kpis as $item)
                <article class="tenant-kpi-card">
                    <div class="tenant-kpi-head">
                        <p class="tenant-kpi-label">{{ $item['label'] }}</p>
                        <span class="tenant-kpi-icon {{ $item['tone'] === 'alert' ? 'is-alert' : $item['tone'] }}" aria-hidden="true">
                            <img src="{{ $item['icon'] }}" alt="">
                        </span>
                    </div>

                    <div class="tenant-kpi-value-wrap">
                        @if (!empty($item['value_prefix']))
                            <span class="tenant-kpi-value-prefix">{{ $item['value_prefix'] }}</span>
                        @endif

                        <h2 class="tenant-kpi-value">{{ $item['value_main'] }}</h2>
                    </div>

                    @if (!empty($item['progress']))
                        <div class="tenant-kpi-progress" aria-hidden="true">
                            <span style="width: {{ $item['progress'] }}%"></span>
                        </div>
                    @endif

                    <p class="tenant-kpi-note is-{{ $item['note_tone'] ?? 'neutral' }}">{{ $item['note'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="tenant-dashboard-content-grid">
            <div class="tenant-dashboard-main">
                <article class="tenant-panel-card tenant-table-card">
                    <div class="tenant-panel-head">
                        <h2 class="tenant-panel-title">Transaksi Terakhir</h2>
                        <a href="{{ route('tenant.transactions.index') }}" class="tenant-panel-link">Lihat Semua</a>
                    </div>

                    <table class="tenant-dashboard-table">
                        <thead>
                            <tr>
                                <th>Keterangan</th>
                                <th>Kategori</th>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactionRows as $row)
                                <tr>
                                    <td class="tenant-transaction-cell">
                                        <span class="tenant-transaction-icon is-{{ $row['type_key'] }}">{{ $row['icon_label'] }}</span>
                                        <div class="tenant-transaction-copy">
                                            <strong>{{ $row['description'] }}</strong>
                                            <span>{{ $row['meta'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tenant-pill is-{{ $row['type_key'] }}">{{ $row['category'] }}</span>
                                    </td>
                                    <td class="tenant-table-date">{{ $row['date'] }}</td>
                                    <td class="tenant-table-amount is-{{ $row['type_key'] }}">{{ $row['amount'] }}</td>
                                    <td class="accounts-action-menu-cell">
                                        <details class="accounts-action-menu" data-dashboard-action-menu>
                                            <summary aria-label="Aksi transaksi">
                                                <img src="{{ asset('images/figma/accounts/kebab.svg') }}" alt="">
                                            </summary>
                                            <div class="accounts-action-popover">
                                                <a href="{{ route('tenant.transactions.index', ['show' => $row['id']]) }}">Lihat Detail</a>
                                                @if ($authUser->role->value === 'owner')
                                                    <a href="{{ route('tenant.transactions.index', ['edit' => $row['id']]) }}">Edit Transaksi</a>
                                                @endif
                                                <a href="{{ route('tenant.transactions.index', ['create' => 1]) }}">Tambah Transaksi</a>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="tenant-dashboard-empty">Belum ada transaksi yang bisa ditampilkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>

                <article class="tenant-panel-card tenant-account-summary-card">
                    <h2 class="tenant-panel-title">Ringkasan Akun</h2>

                    <div class="tenant-account-grid">
                        @forelse ($accountRows as $row)
                            <article class="tenant-account-item {{ $row['is_active'] ? '' : 'is-inactive' }}">
                                <div class="tenant-account-left">
                                    <span class="tenant-account-icon" aria-hidden="true">
                                        <img src="{{ asset('images/figma/accounts/'.$row['icon_asset']) }}" alt="">
                                    </span>
                                    <div>
                                        <p class="tenant-account-name">{{ $row['name'] }}</p>
                                        <p class="tenant-account-type">{{ $row['type'] }}</p>
                                    </div>
                                </div>
                                <div class="tenant-account-right">
                                    <p class="tenant-account-balance">{{ $row['balance'] }}</p>
                                    <p class="tenant-account-status">{{ $row['is_default'] ? 'Default' : ($row['is_active'] ? 'Aktif' : 'Nonaktif') }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="tenant-dashboard-empty">Belum ada akun tenant yang bisa ditampilkan.</p>
                        @endforelse
                    </div>
                </article>
            </div>

            <aside class="tenant-dashboard-side">
                <article class="tenant-panel-card">
                    <h2 class="tenant-panel-title">Aksi Cepat</h2>

                    <div class="tenant-quick-actions">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['href'] }}" class="tenant-quick-action {{ $action['is_primary'] ? 'is-primary' : '' }}">
                                <span class="tenant-quick-action-left">
                                    <span class="tenant-quick-action-icon" aria-hidden="true">
                                        <img src="{{ $action['icon'] }}" alt="">
                                    </span>
                                    <span>{{ $action['label'] }}</span>
                                </span>
                                <span class="tenant-quick-action-arrow" aria-hidden="true">›</span>
                            </a>
                        @endforeach
                    </div>
                </article>

                <article class="tenant-panel-card tenant-chart-card">
                    <div class="tenant-panel-head">
                        <h2 class="tenant-panel-title">Pengeluaran</h2>
                        <span class="tenant-panel-subtitle">Per Minggu</span>
                    </div>

                    <div class="tenant-spending-chart">
                        @foreach ($spendingChart as $item)
                            <div class="tenant-spending-col {{ $item['is_peak'] ? 'is-peak' : '' }}">
                                @if ($item['is_peak'] && $item['value'] > 0)
                                    <span class="tenant-chart-amount">{{ $item['tooltip'] }}</span>
                                @endif
                                <div class="tenant-spending-bar" style="height: {{ $item['height'] }}%"></div>
                                <span>{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="tenant-support-card">
                    <div class="tenant-support-orbit" aria-hidden="true"></div>
                    <h2>Butuh Bantuan?</h2>
                    <p>{{ $supportCopy }}</p>
                    <a href="{{ $supportLink }}" class="tenant-support-button">Hubungi CS</a>
                </article>
            </aside>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const actionMenus = Array.from(document.querySelectorAll('[data-dashboard-action-menu]'));

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
