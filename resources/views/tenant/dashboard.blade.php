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

    <x-ui.table-shell title="Milestone readiness" description="Shell dashboard ini sudah memakai tenant scoping dan siap menampung data nyata milestone berikutnya.">
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
                <td><x-ui.badge tone="warning">Planned</x-ui.badge></td>
                <td>POST route sudah ada, logic create tenant akan diisi pada milestone 1.</td>
            </tr>
            <tr>
                <td>Tenant Login</td>
                <td><x-ui.badge tone="success">Live</x-ui.badge></td>
                <td>Auth guard tenant_users sudah aktif dan bisa diuji dengan akun demo lokal.</td>
            </tr>
            <tr>
                <td>Owner / Member Layout</td>
                <td><x-ui.badge tone="success">Ready</x-ui.badge></td>
                <td>Navigasi memakai role owner/member tanpa AlpineJS.</td>
            </tr>
        </tbody>
    </x-ui.table-shell>
@endsection
