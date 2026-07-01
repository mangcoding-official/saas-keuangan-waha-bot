@extends('layouts.internal')

@section('content')
    <section class="members-summary-grid">
        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Total pesan</p>
            <h2 class="dashboard-kpi-value">{{ $summary['total_messages'] }}</h2>
            <p class="dashboard-kpi-note">Semua pesan yang masuk dari tenant</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Pesan baru</p>
            <h2 class="dashboard-kpi-value">{{ $summary['new_messages'] }}</h2>
            <p class="dashboard-kpi-note">Status `new` yang belum ditindaklanjuti</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Tenant pengirim</p>
            <h2 class="dashboard-kpi-value">{{ $summary['unique_tenants'] }}</h2>
            <p class="dashboard-kpi-note">Workspace unik yang mengirim pesan</p>
        </article>

        <article class="dashboard-kpi-card">
            <p class="dashboard-kpi-label">Masuk hari ini</p>
            <h2 class="dashboard-kpi-value">{{ $summary['today_messages'] }}</h2>
            <p class="dashboard-kpi-note">Traffic support terbaru hari ini</p>
        </article>
    </section>

    <article class="dashboard-card dashboard-table-card">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Tenant / Pengirim</th>
                    <th>WhatsApp</th>
                    <th>Subjek</th>
                    <th>Pesan</th>
                    <th>Status</th>
                    <th>Masuk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['tenant_name'] }}</strong>
                            <div>{{ $row['sender_name'] }}</div>
                        </td>
                        <td>{{ $row['whatsapp_number'] }}</td>
                        <td>{{ $row['subject'] }}</td>
                        <td>
                            <div>{{ \Illuminate\Support\Str::limit($row['message'], 96) }}</div>
                        </td>
                        <td>
                            <x-ui.badge tone="{{ $row['status'] === 'NEW' ? 'warning' : 'neutral' }}">{{ $row['status'] }}</x-ui.badge>
                        </td>
                        <td>{{ $row['created_at'] }}</td>
                        <td>
                            <div class="members-action-stack">
                                <x-ui.button :href="route('internal.support.index', array_merge(request()->query(), ['show' => $row['id']]))" variant="secondary" class="button-compact">Detail</x-ui.button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="dashboard-empty-cell">Belum ada pesan masuk dari Bantuan & Feedback.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </article>

    @if ($messageModal)
        <div class="transactions-modal-backdrop">
            <div class="transactions-modal-card support-detail-modal">
                <div class="transactions-modal-head">
                    <div>
                        <h3>Detail Pesan Support</h3>
                        <p>ID #{{ $messageModal['message']['id'] }} · {{ $messageModal['message']['created_at'] }}</p>
                    </div>
                    <a href="{{ $messageModal['close_url'] }}" aria-label="Tutup modal">&times;</a>
                </div>

                <div class="transactions-modal-form support-detail-body">
                    <div class="support-detail-grid">
                        <div class="support-detail-item">
                            <span class="support-detail-label">Tenant</span>
                            <strong>{{ $messageModal['message']['tenant_name'] }}</strong>
                        </div>
                        <div class="support-detail-item">
                            <span class="support-detail-label">Pengirim</span>
                            <strong>{{ $messageModal['message']['sender_name'] }}</strong>
                        </div>
                        <div class="support-detail-item">
                            <span class="support-detail-label">WhatsApp</span>
                            <strong>{{ $messageModal['message']['whatsapp_number'] }}</strong>
                        </div>
                        <div class="support-detail-item">
                            <span class="support-detail-label">Status</span>
                            <x-ui.badge tone="{{ $messageModal['message']['status'] === 'NEW' ? 'warning' : 'neutral' }}">{{ $messageModal['message']['status'] }}</x-ui.badge>
                        </div>
                    </div>

                    <div class="support-detail-item">
                        <span class="support-detail-label">Subjek</span>
                        <strong>{{ $messageModal['message']['subject'] }}</strong>
                    </div>

                    <div class="support-detail-item">
                        <span class="support-detail-label">Isi Pesan</span>
                        <div class="support-detail-message">{{ $messageModal['message']['message'] }}</div>
                    </div>

                    <div class="transactions-modal-actions">
                        <a href="{{ $messageModal['close_url'] }}" class="transactions-button primary">Tutup</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
