@extends('layouts.base', ['bodyClass' => 'page-internal'])

@section('body')
    <section class="shell-grid">
        <aside class="sidebar" data-sidebar>
            <div>
                <span class="eyebrow">Platform Admin</span>
                <h2 class="panel-title">{{ $authUser->name }}</h2>
                <p class="panel-copy">{{ $authUser->email }}</p>
            </div>

            <nav class="sidebar-nav">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" class="sidebar-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form action="{{ route('internal.logout') }}" method="post">
                @csrf
                <button class="button button-ghost" type="submit">Logout</button>
            </form>
        </aside>

        <div class="content-shell">
            <header class="topbar">
                <div class="topbar-meta">
                    <span class="eyebrow">{{ $page['eyebrow'] ?? 'Internal' }}</span>
                    <h1 class="page-title">{{ $page['title'] ?? '' }}</h1>
                    <p class="page-copy">{{ $page['description'] ?? '' }}</p>
                </div>

                <div class="button-row">
                    <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Navigation</button>
                    <button class="button button-primary" type="button" data-modal-open="internal-contract">Service Boundary</button>
                </div>
            </header>

            @yield('content')
        </div>
    </section>

    <x-ui.modal id="internal-contract" title="Platform Admin Foundation Contract">
        Area `/internal` dipisah dari tenant dashboard. Table filter, modal konfirmasi, dan polling WAHA nanti tetap memakai JavaScript modular biasa.
    </x-ui.modal>
@endsection
