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
            <h2 class="dashboard-section-title">Informasi</h2>

            <div class="dashboard-alert-list">
                <p>Gunakan tipe akun yang paling sesuai dengan sumber dana.</p>
                <p>Aktifkan Set default jika account ini akan dipakai sebagai tujuan utama transaksi baru.</p>
            </div>
        </article>
    </section>
@endsection
