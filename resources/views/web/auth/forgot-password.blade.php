@extends('layouts.base', ['bodyClass' => 'page-home page-login'])

@section('body')
<div class="landing-page login-page">
    @include('web.partials.landing-header')

    <main class="login-main">
        <section class="login-shell">
            <aside class="login-hero-panel">
                <img class="login-hero-image" src="{{ asset('images/login/hero-bg.png') }}" alt="" aria-hidden="true">
                <div class="login-hero-overlay"></div>

                <div class="login-hero-content">
                    <div class="login-hero-copy">
                        <h1>Yuk,</h1>
                        <p>masukkan nomor WhatsApp yang terdaftar agar kami bisa mengirimkan link reset passwordnya.</p>
                    </div>
                </div>
            </aside>

            <section class="login-form-panel">
                <div class="login-card-shell">
                    <article class="login-card">
                        <header class="login-card-head">
                            <p class="login-brand-label">Macau</p>
                            <h1>Lupa password</h1>
                            <p>Masukkan nomor WhatsApp yang terdaftar untuk meminta link reset password.</p>
                        </header>

                        <form action="{{ route('tenant.password.email') }}" method="post" class="login-form">
                            @csrf

                            <label class="login-field" for="whatsapp_number">
                                <span class="login-label">Nomor WhatsApp</span>
                                <span class="login-input-wrap">
                                    <input
                                        id="whatsapp_number"
                                        name="whatsapp_number"
                                        type="text"
                                        class="login-input is-plain"
                                        value="{{ old('whatsapp_number') }}"
                                        placeholder="08xxxxxxxxxx"
                                        inputmode="numeric"
                                        autocomplete="tel"
                                        required
                                    >
                                </span>
                                @error('whatsapp_number')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <button type="submit" class="login-submit">
                                <span>Kirim link reset</span>
                                <img width="12" height="12" src="{{ asset('images/login/icon-arrow-right.svg') }}" alt="" aria-hidden="true">
                            </button>
                        </form>

                        <p class="login-card-helper">
                            Ingat password Anda?
                            <a href="{{ route('tenant.login.create') }}">Kembali ke halaman login</a>
                        </p>
                    </article>

                    <div class="login-meta-links">
                        <a href="{{ route('home') }}#footer">Bantuan</a>
                        <a href="{{ route('home') }}#footer">Privasi</a>
                        <a href="{{ route('home') }}#footer">Syarat &amp; Ketentuan</a>
                    </div>
                </div>
            </section>
        </section>
    </main>

    @include('web.partials.landing-footer')
</div>
@endsection
