@extends('layouts.internal')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total pending</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total_pending'] }}</h2>
            <p class="dashboard-kpi-note">User yang belum verified</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Pending owner</p>
            <h2 class="dashboard-kpi-value">{{ $summary['pending_owners'] }}</h2>
            <p class="dashboard-kpi-note">Owner tenant yang butuh follow-up</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Pending member</p>
            <h2 class="dashboard-kpi-value">{{ $summary['pending_members'] }}</h2>
            <p class="dashboard-kpi-note">Member tenant yang belum aktif</p>
        </article>

        <article class="dashboard-kpi-card {{ $summary['expired_codes'] > 0 ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Expired codes</p>
            <h2 class="dashboard-kpi-value">{{ $summary['expired_codes'] }}</h2>
            <p class="dashboard-kpi-note">Butuh regenerate dari support</p>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Tenant / User</th>
                    <th>Role</th>
                    <th>WhatsApp</th>
                    <th>Activation code</th>
                    <th>Invited by</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['tenant_name'] }}</strong>
                            <div>{{ $row['name'] }}</div>
                            <div class="members-cell-meta">{{ $row['created_at'] }}</div>
                        </td>
                        <td>
                            <div class="members-badge-stack">
                                <x-ui.badge tone="{{ $row['role'] === 'OWNER' ? 'neutral' : 'success' }}">{{ $row['role'] }}</x-ui.badge>
                                <x-ui.badge tone="{{ $row['user_status'] === 'active' ? 'success' : 'warning' }}">{{ $row['user_status'] }}</x-ui.badge>
                            </div>
                        </td>
                        <td>{{ $row['whatsapp_number'] }}</td>
                        <td>
                            @if ($row['activation_last4'])
                                <div>{{ $row['activation_last4'] }}</div>
                                <div class="members-cell-meta">
                                    {{ $row['activation_status'] === 'expired' ? 'Expired' : 'Aktif sampai '.$row['activation_expires_at'] }}
                                </div>
                            @else
                                <span class="members-cell-meta">Tidak ada code aktif</span>
                            @endif
                        </td>
                        <td>{{ $row['inviter_name'] }}</td>
                        <td>
                            <div class="members-action-stack">
                                <form action="{{ route('internal.verification.resend', $row['id']) }}" method="post">
                                    @csrf
                                    <button class="button button-secondary button-compact" type="submit">Resend</button>
                                </form>

                                <form action="{{ route('internal.verification.regenerate', $row['id']) }}" method="post">
                                    @csrf
                                    <button class="button button-ghost button-compact" type="submit">Regenerate</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="dashboard-empty-cell">Tidak ada user pending verification saat ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
