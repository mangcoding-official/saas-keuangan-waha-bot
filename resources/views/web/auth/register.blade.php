@extends('layouts.base', ['bodyClass' => 'page-home page-register'])

@section('body')
<div class="landing-page landing-page-register">
    @include('web.partials.landing-header')

    <main class="register-main">
        <div class="register-atmosphere register-atmosphere-left" aria-hidden="true"></div>
        <div class="register-atmosphere register-atmosphere-right" aria-hidden="true"></div>

        <section class="register-shell">
            <div class="register-intro">
                <h3 class="register-kicker">{{ $page['title'] }}</h3>
                <p class="register-subtitle">{{ $page['description'] }}</p>
            </div>

            <form action="{{ route('tenant.register.store') }}" method="post" class="register-form-shell">
                @csrf

                <section class="register-section-card">
                    <header class="register-section-head">
                        <img src="{{ asset('images/register/info-note.svg') }}" alt="" aria-hidden="true">
                        <h2>Invite Alpha</h2>
                    </header>

                    <div class="register-grid register-grid-owner">
                        <label class="register-field register-field-full" for="invite_code">
                            <span class="register-label">Kode Invite</span>
                            <input id="invite_code" name="invite_code" type="text" class="register-input" value="{{ old('invite_code', $prefilledInviteCode ?? '') }}" placeholder="Contoh: ALPHA-ABCD-EFGH" required>
                            <span class="register-note">
                                <img src="{{ asset('images/register/info-note.svg') }}" alt="" aria-hidden="true">
                                Alpha release hanya menerima registrasi dengan kode invite yang masih aktif.
                            </span>
                            @error('invite_code')
                            <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <p class="register-note">
                        <img src="{{ asset('images/register/info-note.svg') }}" alt="" aria-hidden="true">
                        {{ $inviteGate['message'] }}
                    </p>
                </section>

                <fieldset @disabled(! $inviteGate['is_access_granted'])>
                    <section class="register-section-card">
                        <header class="register-section-head">
                            <img src="{{ asset('images/register/section-workspace.svg') }}" alt="" aria-hidden="true">
                            <h2>Workspace</h2>
                        </header>

                        <div class="register-grid register-grid-workspace">
                            <label class="register-field" for="tenant_name">
                                <span class="register-label">Workspace</span>
                                <input id="tenant_name" name="tenant_name" type="text" class="register-input" value="{{ old('tenant_name') }}" placeholder="Contoh: Keuangan Keluarga" required>
                                @error('tenant_name')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field" for="tenant_type">
                                <span class="register-label">Jenis Penggunaan</span>
                                <span class="register-select-wrap">
                                    <select id="tenant_type" name="tenant_type" class="register-input register-select" required>
                                        <option value="">Pilih jenis penggunaan</option>
                                        @foreach ($tenantTypes as $tenantType)
                                        <option value="{{ $tenantType->value }}" @selected(old('tenant_type', 'personal' )===$tenantType->value)>{{ ucfirst($tenantType->value) }}</option>
                                        @endforeach
                                    </select>
                                    <img src="{{ asset('images/register/chevron-down.svg') }}" alt="" aria-hidden="true">
                                </span>
                                @error('tenant_type')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field register-field-full" for="timezone">
                                <span class="register-label">Zona Waktu</span>
                                <span class="register-select-wrap">
                                    <select id="timezone" name="timezone" class="register-input register-select" required>
                                        @foreach ($timezones as $timezone)
                                        <option value="{{ $timezone['value'] }}" @selected(old('timezone', config('platform.defaults.tenant_timezone'))===$timezone['value'])>{{ $timezone['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <img src="{{ asset('images/register/chevron-down.svg') }}" alt="" aria-hidden="true">
                                </span>
                                @error('timezone')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <section class="register-section-card">
                        <header class="register-section-head">
                            <img src="{{ asset('images/register/section-owner.svg') }}" alt="" aria-hidden="true">
                            <h2>Informasi Akun</h2>
                        </header>

                        <div class="register-grid register-grid-owner">
                            <label class="register-field register-field-full" for="owner_name">
                                <span class="register-label">Nama Lengkap</span>
                                <input id="owner_name" name="owner_name" type="text" class="register-input" value="{{ old('owner_name') }}" placeholder="Masukkan nama" required>
                                @error('owner_name')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field" for="owner_whatsapp">
                                <span class="register-label">Nomor WhatsApp</span>
                                <input
                                    id="owner_whatsapp"
                                    name="owner_whatsapp"
                                    type="text"
                                    class="register-input"
                                    value="{{ old('owner_whatsapp', $inviteGate['locked_whatsapp'] ?? '') }}"
                                    placeholder="0812xxxx"
                                    @readonly($inviteGate['locked_whatsapp'] !== null)
                                    required
                                >
                                <span class="register-note">
                                    <img src="{{ asset('images/register/info-note.svg') }}" alt="" aria-hidden="true">
                                    {{ $inviteGate['locked_whatsapp'] !== null ? 'Nomor WhatsApp ini sudah ditentukan oleh invite.' : 'Digunakan untuk notifikasi transaksi & pemulihan akun.' }}
                                </span>
                                @error('owner_whatsapp')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field" for="owner_email">
                                <span class="register-label">Email</span>
                                <input
                                    id="owner_email"
                                    name="owner_email"
                                    type="email"
                                    class="register-input"
                                    value="{{ old('owner_email', $inviteGate['locked_email'] ?? '') }}"
                                    placeholder="nama@email.com"
                                    @readonly($inviteGate['locked_email'] !== null)
                                    required
                                >
                                @if ($inviteGate['locked_email'] !== null)
                                    <span class="register-note">
                                        <img src="{{ asset('images/register/info-note.svg') }}" alt="" aria-hidden="true">
                                        Email ini sudah ditentukan oleh invite.
                                    </span>
                                @endif
                                @error('owner_email')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field" for="owner_password">
                                <span class="register-label">Password</span>
                                <span class="register-password-wrap">
                                    <input id="owner_password" name="owner_password" type="password" class="register-input" placeholder="********" required>
                                    <img src="{{ asset('images/register/eye.svg') }}" alt="" aria-hidden="true">
                                </span>
                                @error('owner_password')
                                <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="register-field" for="owner_password_confirmation">
                                <span class="register-label">Konfirmasi Password</span>
                                <input id="owner_password_confirmation" name="owner_password_confirmation" type="password" class="register-input" placeholder="********" required>
                            </label>
                        </div>
                    </section>

                    <div class="register-consent">
                        <label class="register-consent-row" for="register_terms">
                            <input id="register_terms" type="checkbox" required>
                            <span>Dengan mencentang kotak ini, Anda menyetujui <strong>Syarat dan Ketentuan</strong> serta <strong>Kebijakan Privasi</strong> kami dalam pengelolaan data keuangan Anda secara aman.</span>
                        </label>

                        <button type="submit" class="register-submit">Buat Akun Sekarang</button>

                        <p class="register-login-copy">Sudah punya akun? <a href="{{ route('tenant.login.create') }}">Masuk di sini</a></p>
                    </div>
                </fieldset>
            </form>
        </section>
    </main>

    @include('web.partials.landing-footer')
</div>
@endsection
