@extends('layouts.base', ['bodyClass' => 'page-internal'])

@section('body')
@php
$searchLabel = $toolbar['search_label'] ?? 'Cari data internal';
$searchPlaceholder = $toolbar['search_placeholder'] ?? 'Search internal workspace';
$secondaryAction = $toolbar['secondary_action'] ?? null;
$primaryAction = $toolbar['primary_action'] ?? null;
@endphp

<section class="shell-grid">
    <aside class="sidebar" data-sidebar>
        <div class="sidebar-brand">
            <span class="sidebar-eyebrow">SUPER ADMIN</span>
            <h2 class="sidebar-title">SaaS Keuangan MACAU Bot</h2>
            <p class="sidebar-copy">Internal operations console</p>
        </div>

        <nav class="sidebar-nav">
            @foreach ($navigation as $item)
            <a href="{{ route($item['route']) }}" class="sidebar-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                <span class="sidebar-link-icon" aria-hidden="true"></span>
                <span>{{ $item['label'] }}</span>
            </a>
            @endforeach
        </nav>

        <form action="{{ route('internal.logout') }}" method="post" class="sidebar-footer">
            @csrf
            <button class="button button-ghost" type="submit">Logout</button>
        </form>
    </aside>

    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-meta">
                <h1 class="page-title">{{ $page['title'] ?? '' }}</h1>
                <p class="page-copy">{{ $page['description'] ?? '' }}</p>
            </div>

            <div class="topbar-toolbar">
                <label class="dashboard-search">
                    <span class="dashboard-search-label">{{ $searchLabel }}</span>
                    <input type="text" class="dashboard-search-input" placeholder="{{ $searchPlaceholder }}">
                </label>

                <div class="button-row">
                    <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>

                    @if ($secondaryAction)
                    <x-ui.button :href="$secondaryAction['href']" :variant="$secondaryAction['variant']">{{ $secondaryAction['label'] }}</x-ui.button>
                    @endif

                    @if ($primaryAction)
                    <x-ui.button :href="$primaryAction['href']" :variant="$primaryAction['variant']">{{ $primaryAction['label'] }}</x-ui.button>
                    @endif
                </div>
            </div>
        </header>

        @yield('content')
    </div>
</section>
@endsection