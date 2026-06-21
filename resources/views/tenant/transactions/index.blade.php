@extends('layouts.tenant')

@section('content')
    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total transaksi</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">Transaksi completed yang terlihat di workspace ini</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Income bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['income'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Akumulasi pemasukan bulan berjalan</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Expense bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['expense'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Akumulasi pengeluaran bulan berjalan</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Transfer bulan ini</p>
            <h2 class="dashboard-kpi-value">Rp {{ number_format($summary['transfer'], 0, ',', '.') }}</h2>
            <p class="dashboard-kpi-note">Mutasi antar akun tenant</p>
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card dashboard-table-card">
            <h2 class="dashboard-section-title">Riwayat transaksi</h2>

            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Nominal</th>
                        <th>Kategori</th>
                        <th>Akun</th>
                        <th>Recorder</th>
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
                                <a href="{{ route('tenant.transactions.index', ['show' => $transaction['id']]) }}" class="button button-secondary button-compact">Detail</a>
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
                <h2 class="dashboard-section-title">Format WhatsApp</h2>

                <div class="dashboard-alert-list">
                    @foreach ($usageExamples as $example)
                        <p>{{ $example }}</p>
                    @endforeach
                </div>
            </article>

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
                        <p><strong>Recorder:</strong> {{ $selectedTransaction['recorder'] }}</p>
                        <p><strong>Logged at:</strong> {{ $selectedTransaction['logged_at'] }}</p>
                        <p><strong>Pesan asli:</strong> {{ $selectedTransaction['description'] }}</p>
                    </div>
                @else
                    <x-ui.state-shell
                        title="Pilih transaksi"
                        description="Klik tombol detail pada tabel untuk melihat sumber akun, tujuan akun, recorder, dan pesan asli WhatsApp."
                        tone="neutral"
                    />
                @endif
            </article>
        </div>
    </section>
@endsection
