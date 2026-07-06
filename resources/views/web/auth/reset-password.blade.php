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
                        <h1>Buat Password Baru Dengan Link Yang Aman</h1>
                        <p>Gunakan link dari WhatsApp untuk membuat password baru. Link hanya berlaku singkat dan tidak bisa dipakai ulang setelah reset selesai.</p>
                    </div>

                    <div class="login-benefit-list">
                        <article class="login-benefit-item">
                            <span class="login-benefit-icon">
                                <img src="{{ asset('images/login/icon-benefit-chart.svg') }}" alt="" aria-hidden="true">
                            </span>
                            <div>
                                <h2>Password Baru Langsung Aktif</h2>
                                <p>Setelah disimpan, Anda bisa langsung login ulang dengan password yang baru.</p>
                            </div>
                        </article>

                        <article class="login-benefit-item">
                            <span class="login-benefit-icon is-users">
                                <img src="{{ asset('images/login/benefit-users-a.svg') }}" alt="" aria-hidden="true">
                            </span>
                            <div>
                                <h2>Link Tidak Bisa Dipakai Ulang</h2>
                                <p>Setiap link reset hanya berlaku untuk satu kali pemakaian.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </aside>

            <section class="login-form-panel">
                <div class="login-card-shell">
                    <article class="login-card">
                        <header class="login-card-head">
                            <p class="login-brand-label">Macau</p>
                            <h1>Buat password baru</h1>
                            <p>{{ $linkMessage }}</p>
                        </header>

                        @if ($isLinkValid)
                            <div class="login-card-note">
                                <span>
                                    @if ($expiresAtIso)
                                        Link ini aktif sampai {{ \Illuminate\Support\Carbon::parse($expiresAtIso)->format('d M Y H:i') }}.
                                    @else
                                        Link reset password tidak valid atau sudah kedaluwarsa.
                                    @endif
                                </span>
                            </div>

                            <form action="{{ route('tenant.password.reset.update', $lookup) }}" method="post" class="login-form">
                                @csrf
                                <input type="hidden" name="token" value="{{ $plainToken }}">

                                <label class="login-field" for="password">
                                    <span class="login-label">Password baru</span>
                                    <span class="login-input-wrap">
                                        <img class="login-input-icon icon-lock" src="{{ asset('images/login/icon-lock.svg') }}" alt="" aria-hidden="true">
                                        <input id="password" name="password" type="password" class="login-input" placeholder="Minimal 8 karakter" required data-password-input autocomplete="new-password">
                                        <button type="button" class="login-password-toggle" data-password-toggle aria-label="Tampilkan atau sembunyikan password">
                                            <img class="login-input-icon icon-eye" src="{{ asset('images/login/icon-eye.svg') }}" alt="" aria-hidden="true">
                                        </button>
                                    </span>
                                    @error('password')
                                        <p class="field-error">{{ $message }}</p>
                                    @enderror
                                </label>

                                <label class="login-field" for="password_confirmation">
                                    <span class="login-label">Konfirmasi password baru</span>
                                    <span class="login-input-wrap">
                                        <img class="login-input-icon icon-lock" src="{{ asset('images/login/icon-lock.svg') }}" alt="" aria-hidden="true">
                                        <input id="password_confirmation" name="password_confirmation" type="password" class="login-input" placeholder="Ulangi password baru" required data-password-input autocomplete="new-password">
                                        <button type="button" class="login-password-toggle" data-password-toggle aria-label="Tampilkan atau sembunyikan password konfirmasi">
                                            <img class="login-input-icon icon-eye" src="{{ asset('images/login/icon-eye.svg') }}" alt="" aria-hidden="true">
                                        </button>
                                    </span>
                                </label>

                                <button type="submit" class="login-submit">
                                    <span>Simpan password baru</span>
                                    <img width="12" height="12" src="{{ asset('images/login/icon-arrow-right.svg') }}" alt="" aria-hidden="true">
                                </button>
                            </form>
                        @else
                            <div class="login-card-note" data-tone="warning">
                                <strong>Link tidak bisa dipakai</strong>
                                <span>{{ $linkMessage }}</span>
                            </div>

                            <div class="login-card-actions">
                                <a href="{{ route('tenant.password.request') }}" class="login-submit">Minta link baru</a>
                                <a href="{{ route('tenant.login.create') }}" class="login-submit is-secondary">Kembali ke login</a>
                            </div>
                        @endif
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
