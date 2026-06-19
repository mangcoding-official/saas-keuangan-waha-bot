@extends('layouts.base', ['bodyClass' => 'page-tenant'])

@section('body')
    <section class="shell-grid">
        <aside class="sidebar" data-sidebar>
            <div>
                <span class="eyebrow">Tenant App</span>
                <h2 class="panel-title">{{ $authUser->tenant->name }}</h2>
                <p class="panel-copy">{{ ucfirst($authUser->role->value) }} · {{ $authUser->email }}</p>
            </div>

            <nav class="sidebar-nav">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" class="sidebar-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form action="{{ route('tenant.logout') }}" method="post">
                @csrf
                <button class="button button-ghost" type="submit">Logout</button>
            </form>
        </aside>

        <div class="content-shell">
            <header class="topbar">
                <div class="topbar-meta">
                    <span class="eyebrow">{{ $page['eyebrow'] ?? 'Tenant' }}</span>
                    <h1 class="page-title">{{ $page['title'] ?? '' }}</h1>
                    <p class="page-copy">{{ $page['description'] ?? '' }}</p>
                </div>

                <div class="button-row">
                    <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Navigation</button>
                    <button class="button button-primary" type="button" data-modal-open="tenant-contract">Milestone Contract</button>
                </div>
            </header>

            @yield('content')
        </div>
    </section>

    <x-ui.modal id="tenant-contract" title="Tenant Foundation Contract">
        Route, guard, dan layout tenant sudah final. Milestone berikutnya tinggal mengisi form logic dan modul bisnis di shell yang sama.
    </x-ui.modal>
@endsection
