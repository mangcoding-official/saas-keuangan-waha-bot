@extends('layouts.tenant')

@section('content')
    <section class="dashboard-kpi-grid">
        @foreach ($kpis as $item)
            <article class="dashboard-kpi-card {{ $item['tone'] === 'alert' ? 'is-alert' : '' }}">
                <p class="dashboard-kpi-label">{{ $item['label'] }}</p>
                <h2 class="dashboard-kpi-value">{{ $item['value'] }}</h2>
                <p class="dashboard-kpi-note">{{ $item['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="dashboard-content-grid">
        <div class="dashboard-main-column">
            <article class="dashboard-card dashboard-table-card">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactionRows as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td>{{ $row['recorder'] }}</td>
                                <td>{{ $row['type'] }}</td>
                                <td>{{ $row['amount'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="dashboard-empty-cell">Belum ada transaksi yang bisa ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="dashboard-card dashboard-table-card">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accountRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['balance'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['note'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="dashboard-empty-cell">Belum ada akun tenant yang bisa ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </div>

        <aside class="dashboard-side-column">
            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Member status</h2>

                <div class="dashboard-stat-list">
                    @foreach ($memberStatus as $row)
                        <p><span>{{ $row['label'] }}</span><span>{{ $row['value'] }}</span></p>
                    @endforeach
                </div>
            </article>

            @if ($pendingBadge)
                <div class="dashboard-inline-badge">
                    <x-ui.badge tone="warning">{{ $pendingBadge }}</x-ui.badge>
                </div>
            @endif

            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Quick actions</h2>

                <div class="dashboard-link-list">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            </article>

            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Alerts</h2>

                <div class="dashboard-alert-list">
                    @foreach ($alerts as $alert)
                        <p>{{ $alert }}</p>
                    @endforeach
                </div>
            </article>
        </aside>
    </section>
@endsection
