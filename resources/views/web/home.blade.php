@extends('layouts.base', ['bodyClass' => 'page-home'])

@section('body')
    <section class="content-shell">
        <x-ui.card>
            <span class="eyebrow">{{ $page['eyebrow'] }}</span>
            <h1 class="hero-title">{{ $page['title'] }}</h1>
            <p class="page-copy">{{ $page['description'] }}</p>

            <div class="button-row">
                <x-ui.button href="{{ route('tenant.register.create') }}">Website Registration</x-ui.button>
                <x-ui.button href="{{ route('tenant.login.create') }}" variant="secondary">Tenant Login</x-ui.button>
                <x-ui.button href="{{ route('internal.login.create') }}" variant="ghost">Platform Admin Login</x-ui.button>
            </div>
        </x-ui.card>

        <div class="card-grid">
            <x-ui.card title="Contract FE/BE" description="Response contract, guard, route prefix, dan shell UI sudah dikunci sebelum fitur bisnis diisi.">
                <x-ui.badge tone="success">Ready for milestone 1</x-ui.badge>
            </x-ui.card>
            <x-ui.card title="Tanpa AlpineJS" description="Interaksi modal, flash, dan sidebar memakai JavaScript modular biasa melalui Vite.">
                <x-ui.badge tone="neutral">Vanilla JS</x-ui.badge>
            </x-ui.card>
            <x-ui.card title="Source of truth" description="PRD, schema, router spec, guided chat spec, dan platform admin IA tetap hidup di root workspace untuk referensi implementasi berikutnya.">
                <x-ui.badge tone="warning">Docs-first</x-ui.badge>
            </x-ui.card>
        </div>
    </section>
@endsection
