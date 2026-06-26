@extends('layouts.base', ['bodyClass' => 'page-tenant'])

@section('body')
    <section class="tenant-shell-grid">
        @include('tenant.partials.sidebar')

        <div class="tenant-content-shell">
            @include('tenant.partials.topbar')
            @yield('content')
        </div>
    </section>
@endsection
