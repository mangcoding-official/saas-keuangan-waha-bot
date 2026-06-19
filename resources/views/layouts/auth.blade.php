@extends('layouts.base', ['bodyClass' => 'page-auth'])

@section('body')
    <section class="auth-shell">
        <aside class="auth-hero">
            <div>
                <span class="eyebrow">{{ $page['eyebrow'] ?? 'Foundation' }}</span>
                <h1 class="hero-title">{{ $page['title'] ?? config('app.name') }}</h1>
                <p class="hero-copy">{{ $page['description'] ?? '' }}</p>
            </div>

            <div class="auth-checklist">
                <div class="panel">
                    <h2 class="panel-title">Contract yang sudah dikunci</h2>
                    <p class="panel-copy">Guard tenant, guard platform admin, route prefix `/app` dan `/internal`, serta UI shell tanpa AlpineJS.</p>
                </div>
                <div class="panel">
                    <h2 class="panel-title">Milestone berikutnya</h2>
                    <p class="panel-copy">Form action yang masih placeholder akan diisi logic bisnis riil per milestone vertical flow.</p>
                </div>
            </div>
        </aside>

        <main class="auth-panel">
            @yield('content')
        </main>
    </section>
@endsection
