@extends('layouts.internal')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total invites</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total'] }}</h2>
            <p class="dashboard-kpi-note">Semua invite onboarding alpha</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Pending</p>
            <h2 class="dashboard-kpi-value">{{ $summary['pending'] }}</h2>
            <p class="dashboard-kpi-note">Masih bisa dipakai register</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Used</p>
            <h2 class="dashboard-kpi-value">{{ $summary['used'] }}</h2>
            <p class="dashboard-kpi-note">Sudah dipakai owner baru</p>
        </article>

        <article class="dashboard-kpi-card {{ $summary['blocked'] > 0 ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Expired / Revoked</p>
            <h2 class="dashboard-kpi-value">{{ $summary['blocked'] }}</h2>
            <p class="dashboard-kpi-note">Butuh invite baru bila onboarding lanjut</p>
        </article>
    </section>

    <section class="invite-layout-grid">
        <article class="dashboard-card invite-form-card">
            <h2 class="dashboard-section-title">Buat Invite Baru</h2>

            <form action="{{ route('internal.invites.store') }}" method="post" class="invite-form-grid">
                @csrf

                <label class="field field-full" for="invited_email">
                    <span class="field-label">Email Target</span>
                    <input id="invited_email" name="invited_email" type="email" class="input" value="{{ old('invited_email') }}" placeholder="Opsional, batasi invite ke email tertentu">
                    <p class="field-help">Kosongkan bila invite boleh dipakai oleh siapa saja yang memegang kode.</p>
                </label>

                <label class="field" for="expires_at">
                    <span class="field-label">Berlaku Sampai</span>
                    <input id="expires_at" name="expires_at" type="datetime-local" class="input" value="{{ old('expires_at') }}">
                </label>

                <label class="field" for="note">
                    <span class="field-label">Catatan</span>
                    <input id="note" name="note" type="text" class="input" value="{{ old('note') }}" placeholder="Opsional, mis. batch alpha minggu ini">
                </label>

                <div class="members-form-actions">
                    <button class="button button-primary" type="submit">Generate Invite</button>
                </div>
            </form>
        </article>

        <article class="dashboard-card invite-tip-card">
            <h2 class="dashboard-section-title">Cara Pakai</h2>
            <div class="dashboard-alert-list">
                <p>Buat invite untuk calon owner alpha.</p>
                <p>Bagikan kode invite atau link register yang sudah berisi kode otomatis.</p>
                <p>Revoke invite bila onboarding dibatalkan atau kode terlanjur tersebar.</p>
            </div>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Invite</th>
                    <th>Status</th>
                    <th>Target</th>
                    <th>Usage</th>
                    <th>Share</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['code'] }}</strong>
                            <div class="members-cell-meta">Dibuat {{ $row['created_at'] }} oleh {{ $row['creator_name'] }}</div>
                            @if ($row['note'])
                                <div class="members-cell-meta">{{ $row['note'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="members-badge-stack">
                                <x-ui.badge tone="{{ $row['status'] === 'pending' ? 'success' : ($row['status'] === 'used' ? 'neutral' : 'warning') }}">{{ $row['status'] }}</x-ui.badge>
                            </div>
                            <div class="members-cell-meta">
                                @if ($row['expires_at'])
                                    Berlaku sampai {{ $row['expires_at'] }}
                                @elseif ($row['status'] === 'pending')
                                    Tanpa batas waktu
                                @endif
                            </div>
                        </td>
                        <td>
                            <div>{{ $row['invited_email'] ?: 'Semua email' }}</div>
                            @if ($row['revoked_at'])
                                <div class="members-cell-meta">Revoked {{ $row['revoked_at'] }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($row['used_tenant_name'])
                                <div>{{ $row['used_tenant_name'] }}</div>
                                <div class="members-cell-meta">Dipakai {{ $row['used_at'] }}</div>
                            @else
                                <span class="members-cell-meta">Belum dipakai</span>
                            @endif
                        </td>
                        <td>
                            <div class="invite-share-stack">
                                <button
                                    type="button"
                                    class="button button-secondary button-compact"
                                    data-copy-text="{{ $row['code'] }}"
                                    data-copy-label="Copy Code"
                                    data-copy-success-label="Copied"
                                >
                                    Copy Code
                                </button>
                                <button
                                    type="button"
                                    class="button button-ghost button-compact"
                                    data-copy-text="{{ $row['share_url'] }}"
                                    data-copy-label="Copy Link"
                                    data-copy-success-label="Copied"
                                >
                                    Copy Link
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="members-action-stack">
                                @if ($row['status'] === 'pending')
                                    <form action="{{ route('internal.invites.revoke', $row['id']) }}" method="post" class="invite-revoke-form">
                                        @csrf
                                        <input type="text" name="reason" class="input input-compact" placeholder="Opsional: alasan revoke">
                                        <button class="button button-ghost button-compact" type="submit">Revoke</button>
                                    </form>
                                @else
                                    <span class="members-cell-meta">Tidak ada aksi</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="dashboard-empty-cell">Belum ada invite owner registration.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
