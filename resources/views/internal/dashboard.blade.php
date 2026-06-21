@extends('layouts.internal')

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

    <section class="dashboard-content-grid dashboard-content-grid-admin">
        <div class="dashboard-main-column">
            <article class="dashboard-card dashboard-flow-card">
                <h2 class="dashboard-section-title">Attention queue</h2>

                <div class="dashboard-alert-list">
                    @foreach ($attentionQueueSummary as $item)
                        <p>{{ $item }}</p>
                    @endforeach
                </div>
            </article>

            <article class="dashboard-card dashboard-table-card">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Queue</th>
                            <th>Tenant / User</th>
                            <th>Status</th>
                            <th>Next action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attentionQueueRows as $row)
                            <tr>
                                <td>{{ $row['queue'] }}</td>
                                <td>{{ $row['tenant_user'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['next_action'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="dashboard-empty-cell">Belum ada antrian yang perlu di-review.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="dashboard-card dashboard-table-card">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Recent internal actions</th>
                            <th>Actor</th>
                            <th>Target</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInternalActions as $row)
                            <tr>
                                <td>{{ $row['action'] }}</td>
                                <td>{{ $row['actor'] }}</td>
                                <td>{{ $row['target'] }}</td>
                                <td>{{ $row['time'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="dashboard-empty-cell">Belum ada audit internal yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </div>

        <aside class="dashboard-side-column">
            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Platform status</h2>

                <div class="dashboard-alert-list">
                    @foreach ($platformStatus as $item)
                        <p>{{ $item }}</p>
                    @endforeach
                </div>
            </article>

            <div class="dashboard-inline-badge">
                <x-ui.badge tone="{{ $statusBadge === 'waha healthy' ? 'success' : 'warning' }}">{{ $statusBadge }}</x-ui.badge>
            </div>

            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Verification snapshot</h2>

                <div class="dashboard-alert-list">
                    @foreach ($verificationSnapshot as $item)
                        <p>{{ $item }}</p>
                    @endforeach
                </div>
            </article>

            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Service snapshot</h2>

                <div class="dashboard-alert-list">
                    @foreach ($serviceSnapshot as $item)
                        <p>{{ $item }}</p>
                    @endforeach
                </div>
            </article>
        </aside>
    </section>
@endsection
