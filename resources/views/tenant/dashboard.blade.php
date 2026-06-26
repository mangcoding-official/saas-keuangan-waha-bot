@extends('layouts.tenant')

@section('content')
    <section class="tenant-dashboard-kpi-grid">
        @foreach ($kpis as $item)
            <article class="tenant-kpi-card {{ $item['tone'] === 'alert' ? 'is-alert' : '' }}">
                <p class="tenant-kpi-label">{{ $item['label'] }}</p>
                <h2 class="tenant-kpi-value">{{ $item['value'] }}</h2>
                <p class="tenant-kpi-note">{{ $item['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="tenant-dashboard-content-grid">
        <div class="tenant-dashboard-main">
            <article class="tenant-panel-card">
                <div class="tenant-panel-head">
                    <h2 class="tenant-panel-title">Transaksi Terakhir</h2>
                    <a href="{{ route('tenant.transactions.index') }}" class="tenant-panel-link">Lihat Semua</a>
                </div>

                <table class="tenant-dashboard-table">
                    <thead>
                        <tr>
                            <th>Keterangan</th>
                            <th>Pengguna</th>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactionRows as $row)
                            <tr>
                                <td>{{ $row['description'] }}</td>
                                <td>{{ $row['recorder'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td>
                                    <span class="tenant-pill">{{ $row['type'] }}</span>
                                </td>
                                <td class="{{ str_contains($row['amount'], '-') ? 'is-expense' : 'is-income' }}">{{ $row['amount'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="tenant-dashboard-empty">Belum ada transaksi yang bisa ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="tenant-panel-card">
                <h2 class="tenant-panel-title">Ringkasan Akun</h2>

                <div class="tenant-account-grid">
                    @forelse ($accountRows as $row)
                        <article class="tenant-account-item">
                            <div>
                                <p class="tenant-account-name">{{ $row['name'] }}</p>
                                <p class="tenant-account-note">{{ $row['note'] }}</p>
                            </div>
                            <div class="tenant-account-right">
                                <p class="tenant-account-balance">{{ $row['balance'] }}</p>
                                <p class="tenant-account-status">{{ $row['status'] }}</p>
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
                        <a href="{{ $action['href'] }}" class="tenant-quick-action">
                            <span>{{ $action['label'] }}</span>
                            <span aria-hidden="true">›</span>
                        </a>
                    @endforeach
                </div>
            </article>

            <article class="tenant-panel-card">
                <div class="tenant-panel-head">
                    <h2 class="tenant-panel-title">Pengeluaran</h2>
                    <span class="tenant-panel-subtitle">Per Minggu</span>
                </div>

                <div class="tenant-spending-chart">
                    @foreach ($spendingChart as $item)
                        <div class="tenant-spending-col {{ $item['is_peak'] ? 'is-peak' : '' }}">
                            <div class="tenant-spending-bar" style="height: {{ $item['height'] }}%"></div>
                            <span>{{ $item['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            @if ($pendingBadge)
                <div class="tenant-inline-badge">
                    <x-ui.badge tone="warning">{{ $pendingBadge }}</x-ui.badge>
                </div>
            @endif

            <article class="tenant-support-card">
                <h2>Butuh Bantuan?</h2>
                <p>Tim kami siap membantu Anda mengelola keuangan tenant dengan aman.</p>
                <a href="{{ $supportLink }}" class="tenant-support-button">Hubungi CS</a>
            </article>

            <article class="tenant-panel-card">
                <h2 class="tenant-panel-title">Alerts</h2>
                <div class="tenant-alert-list">
                    @foreach ($alerts as $alert)
                        <p>{{ $alert }}</p>
                    @endforeach
                </div>
            </article>
        </aside>
    </section>
@endsection
