@extends('layouts.auth')

@section('content')
    <span class="eyebrow">{{ $page['eyebrow'] }}</span>
    <h1 class="page-title">{{ $page['title'] }}</h1>
    <p class="page-copy">{{ $page['description'] }}</p>

    @if ($demoCredentials)
        <x-ui.card title="Demo local credentials" description="Akun local ini membantu verifikasi guard internal dan shell support sebelum fitur support penuh dibangun.">
            <p class="panel-copy">Email: <strong>{{ $demoCredentials['email'] }}</strong></p>
            <p class="panel-copy">Password: <strong>{{ $demoCredentials['password'] }}</strong></p>
        </x-ui.card>
    @endif

    <form action="{{ route('internal.login.store') }}" method="post" class="field-grid">
        @csrf
        <x-ui.input name="email" label="Email Internal" type="email" placeholder="admin@demo.test" full required />
        <x-ui.input name="password" label="Password" type="password" full required />

        <label class="checkbox-row field-full">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat sesi platform admin</span>
        </label>

        <div class="field field-full">
            <x-ui.button type="submit">Masuk ke Platform Admin</x-ui.button>
        </div>
    </form>
@endsection
