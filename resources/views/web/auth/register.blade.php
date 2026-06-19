@extends('layouts.auth')

@section('content')
    <span class="eyebrow">{{ $page['eyebrow'] }}</span>
    <h1 class="page-title">{{ $page['title'] }}</h1>
    <p class="page-copy">{{ $page['description'] }}</p>

    <form action="{{ route('tenant.register.store') }}" method="post" class="field-grid">
        @csrf
        <x-ui.input name="tenant_name" label="Nama Tenant" placeholder="Contoh: Keuangan Toko Sari" required />
        <x-ui.input name="tenant_type" label="Jenis Tenant" placeholder="personal, umkm, company" required />
        <x-ui.input name="owner_name" label="Nama Owner" placeholder="Nama lengkap owner" required />
        <x-ui.input name="owner_whatsapp" label="Nomor WhatsApp Owner" placeholder="08xxxxxxxxxx" required />
        <x-ui.input name="owner_email" label="Email Owner" type="email" placeholder="owner@contoh.test" required />
        <x-ui.input name="owner_password" label="Password Owner" type="password" required />

        <div class="field field-full">
            <x-ui.button type="submit">Simpan Contract Route</x-ui.button>
        </div>
    </form>
@endsection
