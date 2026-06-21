@extends('layouts.auth')

@section('content')
    <span class="eyebrow">{{ $page['eyebrow'] }}</span>
    <h1 class="page-title">{{ $page['title'] }}</h1>
    <p class="page-copy">{{ $page['description'] }}</p>

    <x-ui.card title="Flow registrasi milestone 1" description="Registrasi website akan membuat tenant, owner, activation code, akun Cash default, dan kategori template dasar. Owner login dashboard tetap bisa dilakukan walau nomor masih pending verification.">
        <x-ui.badge tone="success">No AlpineJS</x-ui.badge>
    </x-ui.card>

    <form action="{{ route('tenant.register.store') }}" method="post" class="field-grid">
        @csrf
        <x-ui.input name="tenant_name" label="Nama Tenant" placeholder="Contoh: Keuangan Toko Sari" required />
        <label class="field" for="tenant_type">
            <span class="field-label">Jenis Tenant</span>
            <select id="tenant_type" name="tenant_type" class="input-control" required>
                <option value="">Pilih jenis tenant</option>
                @foreach ($tenantTypes as $tenantType)
                    <option value="{{ $tenantType->value }}" @selected(old('tenant_type') === $tenantType->value)>{{ ucfirst($tenantType->value) }}</option>
                @endforeach
            </select>
            @error('tenant_type')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </label>
        <label class="field" for="timezone">
            <span class="field-label">Timezone Tenant</span>
            <select id="timezone" name="timezone" class="input-control" required>
                @foreach ($timezones as $timezone)
                    <option value="{{ $timezone }}" @selected(old('timezone', config('platform.defaults.tenant_timezone')) === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
            <p class="field-help">Timezone ini akan dipakai untuk tanggal transaksi tenant.</p>
            @error('timezone')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </label>
        <x-ui.input name="owner_name" label="Nama Owner" placeholder="Nama lengkap owner" required />
        <x-ui.input name="owner_whatsapp" label="Nomor WhatsApp Owner" placeholder="08xxxxxxxxxx" required />
        <x-ui.input name="owner_email" label="Email Owner" type="email" placeholder="owner@contoh.test" required />
        <x-ui.input name="owner_password" label="Password Owner" type="password" required />
        <x-ui.input name="owner_password_confirmation" label="Konfirmasi Password" type="password" required />

        <div class="field field-full">
            <x-ui.button type="submit">Daftar</x-ui.button>
        </div>
    </form>
@endsection
