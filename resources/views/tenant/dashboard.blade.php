@extends('layouts.tenant')

@section('content')
    <section class="stat-grid">
        @foreach ($stats as $stat)
            <x-ui.card>
                <x-ui.badge :tone="$stat['tone']">{{ $stat['label'] }}</x-ui.badge>
                <h2 class="page-title">{{ $stat['value'] }}</h2>
            </x-ui.card>
        @endforeach
    </section>

    <div class="card-grid">
        <x-ui.card title="Tenant Summary" description="Data ini berasal dari tenant dan owner yang sedang login, bukan placeholder seed layout.">
            <div class="table-meta">
                <p class="panel-copy"><strong>Tenant</strong>: {{ $tenantSummary['tenant_name'] }}</p>
                <p class="panel-copy"><strong>Type</strong>: {{ ucfirst($tenantSummary['tenant_type']) }}</p>
                <p class="panel-copy"><strong>Timezone</strong>: {{ $tenantSummary['timezone'] }}</p>
                <p class="panel-copy"><strong>Email Owner</strong>: {{ $tenantSummary['owner_email'] }}</p>
                <p class="panel-copy"><strong>WhatsApp Owner</strong>: {{ $tenantSummary['owner_whatsapp'] }}</p>
            </div>
        </x-ui.card>

        <x-ui.card title="Verification State" description="Owner dashboard tetap bisa diakses, tetapi akses bot baru penuh setelah nomor berhasil diverifikasi via activation code.">
            <div class="table-meta">
                <x-ui.badge :tone="$tenantSummary['verification_status'] === 'verified' ? 'success' : 'warning'">{{ $tenantSummary['verification_status'] }}</x-ui.badge>
                @if ($tenantSummary['active_code_last4'])
                    <p class="panel-copy"><strong>Activation Code Last4</strong>: {{ $tenantSummary['active_code_last4'] }}</p>
                    <p class="panel-copy"><strong>Expired At</strong>: {{ \Illuminate\Support\Carbon::parse($tenantSummary['active_code_expires_at'])->timezone($tenantSummary['timezone'])->format('d M Y H:i') }}</p>
                    <p class="panel-copy">Kode aktivasi penuh hanya ditampilkan sekali saat registrasi sukses. Dashboard hanya menampilkan petunjuk status dan last4 untuk verifikasi operasional.</p>
                @else
                    <p class="panel-copy">Belum ada activation code aktif untuk user ini.</p>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Dashboard Boundary" description="{{ $tenantSummary['dashboard_role_note'] }}">
            <div class="table-meta">
                <x-ui.badge :tone="$tenantSummary['service_status'] === 'active' ? 'success' : 'danger'">{{ $tenantSummary['service_status'] }}</x-ui.badge>
                <p class="panel-copy">Tenant scoping sekarang aktif berdasarkan `tenant_id` dari sesi login.</p>
            </div>
        </x-ui.card>
    </div>

    <x-ui.table-shell title="Milestone 1 readiness" description="Flow website registration dan owner login sudah berjalan dengan data onboarding nyata.">
        <thead>
            <tr>
                <th>Flow</th>
                <th>Status</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Website Registration</td>
                <td><x-ui.badge tone="success">Live</x-ui.badge></td>
                <td>Registrasi sekarang membuat tenant, owner, activation code, akun Cash default, dan kategori template.</td>
            </tr>
            <tr>
                <td>Tenant Login</td>
                <td><x-ui.badge tone="success">Live</x-ui.badge></td>
                <td>Auth guard `tenant_users` aktif dan owner baru bisa login ke dashboard tenant.</td>
            </tr>
            <tr>
                <td>Tenant Scoping</td>
                <td><x-ui.badge tone="success">Ready</x-ui.badge></td>
                <td>Dashboard hanya membaca data tenant milik user yang sedang login.</td>
            </tr>
        </tbody>
    </x-ui.table-shell>
@endsection
