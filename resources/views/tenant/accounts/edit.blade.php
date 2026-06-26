@extends('layouts.tenant')

@section('content')
    @if ($errors->any())
        <div class="members-alert members-alert-warning">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="members-layout-grid">
        @include('tenant.accounts.partials.account-form')

        <article class="dashboard-card">
            <h2 class="dashboard-section-title">Status Account</h2>

            <div class="dashboard-alert-list">
                <p>Status aktif/nonaktif account tetap dikelola dari halaman daftar account.</p>
                <p>Set default di halaman ini akan menggantikan default account sebelumnya secara otomatis.</p>
            </div>
        </article>
    </section>
@endsection
