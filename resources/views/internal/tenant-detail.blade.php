@extends('layouts.internal')

@section('content')
    <section class="members-summary-grid">
        @foreach ($summary as $item)
            <article class="dashboard-kpi-card {{ ($item['tone'] ?? 'neutral') === 'alert' ? 'is-alert' : '' }}">
                <p class="dashboard-kpi-label">{{ $item['label'] }}</p>
                <h2 class="dashboard-kpi-value">{{ $item['value'] }}</h2>
                <p class="dashboard-kpi-note">{{ $item['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Tenant status</h2>

            <div class="members-badge-stack">
                <x-ui.badge tone="{{ $tenantMeta['tenant_status'] === 'active' ? 'success' : 'warning' }}">{{ $tenantMeta['tenant_status'] }}</x-ui.badge>
                <x-ui.badge tone="{{ $tenantMeta['service_status'] === 'active' ? 'success' : 'warning' }}">{{ $tenantMeta['service_status'] }}</x-ui.badge>
                <x-ui.badge tone="{{ $tenantMeta['ai_addon_status'] === 'active' ? 'success' : 'neutral' }}">{{ $tenantMeta['ai_addon_status'] }}</x-ui.badge>
            </div>

            <div class="dashboard-alert-list">
                <p><strong>Type:</strong> {{ $tenantMeta['type'] }}</p>
                <p><strong>Plan:</strong> {{ strtoupper($tenantMeta['service_plan']) }}</p>
                <p><strong>Timezone:</strong> {{ $tenantMeta['timezone'] }}</p>
                <p><strong>Created:</strong> {{ $tenantMeta['created_at'] }}</p>
                <p><strong>Updated:</strong> {{ $tenantMeta['updated_at'] }}</p>
            </div>

            <hr class="dashboard-divider">

            <h2 class="dashboard-section-title">Bot assignments</h2>

            @if ($botAssignments !== [])
                <div class="dashboard-alert-list">
                    @foreach ($botAssignments as $item)
                        <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                        @foreach ($item['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @endforeach
                </div>
            @else
                <p class="dashboard-kpi-note">Belum ada assignment bot aktif untuk tenant ini.</p>
            @endif
        </article>

        <div class="dashboard-side-column">
            <article class="dashboard-card">
                <h2 class="dashboard-section-title">Users</h2>

                @if ($users !== [])
                    <div class="dashboard-alert-list">
                        @foreach ($users as $item)
                            <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                            <div class="members-badge-stack">
                                @foreach ($item['badges'] as $badge)
                                    <x-ui.badge :tone="$badge['tone']">{{ $badge['label'] }}</x-ui.badge>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="dashboard-kpi-note">Belum ada user pada tenant ini.</p>
                @endif
            </article>
        </div>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Recent sessions</h2>

            @if ($sessions !== [])
                <div class="dashboard-alert-list">
                    @foreach ($sessions as $item)
                        <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                        @foreach ($item['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @endforeach
                </div>
            @else
                <p class="dashboard-kpi-note">Tidak ada session terbaru.</p>
            @endif
        </article>

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Recent transactions</h2>

            @if ($transactions !== [])
                <div class="dashboard-alert-list">
                    @foreach ($transactions as $item)
                        <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                        <div class="members-badge-stack">
                            @foreach ($item['badges'] as $badge)
                                <x-ui.badge :tone="$badge['tone']">{{ $badge['label'] }}</x-ui.badge>
                            @endforeach
                        </div>
                        @foreach ($item['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @endforeach
                </div>
            @else
                <p class="dashboard-kpi-note">Belum ada transaksi.</p>
            @endif
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Recent attachments</h2>

            @if ($attachments !== [])
                <div class="dashboard-alert-list">
                    @foreach ($attachments as $item)
                        <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                        @foreach ($item['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @endforeach
                </div>
            @else
                <p class="dashboard-kpi-note">Belum ada attachment.</p>
            @endif
        </article>

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Recent tenant audit</h2>

            @if ($recentAudit !== [])
                <div class="dashboard-alert-list">
                    @foreach ($recentAudit as $item)
                        <p><strong>{{ $item['title'] }}</strong> · {{ $item['meta'] }}</p>
                        @foreach ($item['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    @endforeach
                </div>
            @else
                <p class="dashboard-kpi-note">Belum ada audit log.</p>
            @endif
        </article>
    </section>
@endsection
