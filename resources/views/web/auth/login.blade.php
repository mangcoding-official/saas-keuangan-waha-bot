@extends('layouts.auth')

@section('content')
    <span class="eyebrow">{{ $page['eyebrow'] }}</span>
    <h1 class="page-title">{{ $page['title'] }}</h1>
    <p class="page-copy">{{ $page['description'] }}</p>

    @if ($demoCredentials)
        <x-ui.card title="Demo local credentials" description="Akun ini berasal dari seeder foundation agar guard dan layout bisa diuji lebih awal.">
            <p class="panel-copy">Email: <strong>{{ $demoCredentials['email'] }}</strong></p>
            <p class="panel-copy">Password: <strong>{{ $demoCredentials['password'] }}</strong></p>
        </x-ui.card>
    @endif

    <form action="{{ route('tenant.login.store') }}" method="post" class="field-grid">
        @csrf
        <x-ui.input name="email" label="Email" type="email" placeholder="owner@demo.test" full required />
        <x-ui.input name="password" label="Password" type="password" full required />

        <label class="checkbox-row field-full">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat sesi login tenant</span>
        </label>

        <div class="field field-full">
            <x-ui.button type="submit">Masuk ke Tenant Dashboard</x-ui.button>
        </div>
    </form>
@endsection
