@extends('layouts.internal')

@section('content')
    <section class="stat-grid">
        @foreach ($stats as $stat)
            <x-ui.card>
                <x-ui.badge :tone="$stat['tone']">{{ $stat['label'] }}</x-ui.badge>
                <h2 class="page-title">{{ $stat['value'] }}</h2>
            </x-ui.card>
        @endforeach
    </section>

    <x-ui.table-shell title="Support overview contract" description="Overview internal ini mengikuti information architecture platform admin dan siap menerima data operasional nyata.">
        <thead>
            <tr>
                <th>Module</th>
                <th>Boundary</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Tenants & Users</td>
                <td>Lookup lintas tenant dengan guard terpisah.</td>
                <td><x-ui.badge tone="success">Mapped</x-ui.badge></td>
            </tr>
            <tr>
                <td>Verification & Sessions</td>
                <td>Shell siap untuk pending verification dan guided chat monitoring.</td>
                <td><x-ui.badge tone="warning">Next milestone</x-ui.badge></td>
            </tr>
            <tr>
                <td>WAHA Control</td>
                <td>UI shell siap, service boundary sudah didokumentasikan.</td>
                <td><x-ui.badge tone="success">Ready</x-ui.badge></td>
            </tr>
        </tbody>
    </x-ui.table-shell>
@endsection
