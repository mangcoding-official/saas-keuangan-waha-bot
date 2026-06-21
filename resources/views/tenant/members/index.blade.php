@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total user tenant</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total_users'] }}</h2>
            <p class="dashboard-kpi-note">Limit {{ $summary['limit'] }} user per tenant</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Member aktif + pending</p>
            <h2 class="dashboard-kpi-value">{{ $summary['member_count'] }}</h2>
            <p class="dashboard-kpi-note">Tidak termasuk owner</p>
        </article>

        <article class="dashboard-kpi-card {{ $summary['pending_count'] > 0 ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Pending verification</p>
            <h2 class="dashboard-kpi-value">{{ $summary['pending_count'] }}</h2>
            <p class="dashboard-kpi-note">Butuh activation code valid</p>
        </article>

        <article class="dashboard-kpi-card {{ $slotIsFull ? 'is-alert' : '' }}">
            <p class="dashboard-kpi-label">Sisa slot</p>
            <h2 class="dashboard-kpi-value">{{ $summary['remaining_slots'] }}</h2>
            <p class="dashboard-kpi-note">{{ $slotIsFull ? 'Tenant sudah penuh' : 'Masih bisa tambah member' }}</p>
        </article>
    </section>

    <section class="members-layout-grid">
        <article class="dashboard-card members-form-card">
            <h2 class="dashboard-section-title">Add member</h2>
            <p class="panel-copy">Owner menambahkan member dengan nama dan nomor WhatsApp. Member baru langsung masuk `pending_verification` dan mendapat activation code 15 menit.</p>

            @if ($slotIsFull)
                <div class="members-alert members-alert-warning">
                    Slot user tenant sudah penuh. Maksimal 1 owner dan 4 member.
                </div>
            @endif

            <form action="{{ route('tenant.members.store') }}" method="post" class="field-grid">
                @csrf

                <x-ui.input
                    name="name"
                    label="Nama member"
                    placeholder="Contoh: Budi Finance"
                    :disabled="$slotIsFull"
                    required
                />

                <x-ui.input
                    name="whatsapp_number"
                    label="Nomor WhatsApp"
                    placeholder="08xxxxxxxxxx"
                    :disabled="$slotIsFull"
                    required
                />

                <div class="field field-full">
                    <div class="members-form-actions">
                        <button class="button button-primary" type="submit" @disabled($slotIsFull)>Tambah member</button>
                        <a href="{{ route('tenant.dashboard') }}" class="button button-secondary">Kembali ke overview</a>
                    </div>
                </div>
            </form>
        </article>

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Verification rules</h2>

            <div class="dashboard-alert-list">
                <p>Satu activation code aktif per user.</p>
                <p>Code berlaku {{ config('platform.timeouts.activation_code_minutes') }} menit.</p>
                <p>Nomor bot aktif tidak boleh dipakai sebagai nomor member.</p>
                <p>Nomor yang sama tidak boleh dipakai lintas tenant.</p>
                <p>User `pending_verification` belum boleh memakai guided chat atau parser transaksi.</p>
            </div>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>WhatsApp</th>
                    <th>Verification</th>
                    <th>Activation code</th>
                    <th>Invited by</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>
                            <strong>{{ $member['name'] }}</strong>
                            <div class="members-cell-meta">{{ $member['created_at'] }}</div>
                        </td>
                        <td>
                            <div class="members-badge-stack">
                                <x-ui.badge tone="{{ $member['role'] === 'OWNER' ? 'neutral' : 'success' }}">{{ $member['role'] }}</x-ui.badge>
                                <x-ui.badge tone="{{ $member['user_status'] === 'active' ? 'success' : 'danger' }}">{{ $member['user_status'] }}</x-ui.badge>
                            </div>
                        </td>
                        <td>{{ $member['whatsapp_number'] }}</td>
                        <td>
                            <x-ui.badge tone="{{ $member['verification_status'] === 'verified' ? 'success' : 'warning' }}">
                                {{ $member['verification_status'] }}
                            </x-ui.badge>
                        </td>
                        <td>
                            @if ($member['activation_last4'])
                                <div>{{ $member['activation_last4'] }}</div>
                                <div class="members-cell-meta">
                                    {{ $member['activation_status'] === 'expired' ? 'Expired' : 'Aktif sampai '.$member['activation_expires_at'] }}
                                </div>
                            @else
                                <span class="members-cell-meta">Tidak ada code aktif</span>
                            @endif
                        </td>
                        <td>{{ $member['inviter_name'] }}</td>
                        <td>
                            @if ($member['can_manage_code'])
                                <div class="members-action-stack">
                                    <form action="{{ route('tenant.members.resend', $member['id']) }}" method="post">
                                        @csrf
                                        <button class="button button-secondary button-compact" type="submit">Resend</button>
                                    </form>

                                    <form action="{{ route('tenant.members.regenerate', $member['id']) }}" method="post">
                                        @csrf
                                        <button class="button button-ghost button-compact" type="submit">Regenerate</button>
                                    </form>
                                </div>
                            @else
                                <span class="members-cell-meta">Tidak ada action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="dashboard-empty-cell">Belum ada member untuk tenant ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>
@endsection
