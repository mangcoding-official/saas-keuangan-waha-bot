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
                        <h1>Catat dan Kelola Keuangan Lebih Praktis</h1>
                        <p>Catat pemasukan, pengeluaran, dan transfer dengan lebih praktis melalui WhatsApp. Semua transaksi tersimpan rapi dan dapat dipantau kembali melalui dashboard. Mulai tata catatan keuangan Anda hari ini.</p>
                    </div>

                    <div class="login-benefit-list">
                        <article class="login-benefit-item">
                            <span class="login-benefit-icon">
                                <img src="{{ asset('images/login/icon-benefit-chart.svg') }}" alt="" aria-hidden="true">
                            </span>
                            <div>
                                <h2>Pantau Catatan Terkini</h2>
                                <p>Lihat pemasukan, pengeluaran, transfer, dan saldo terbaru langsung melalui dashboard.</p>
                            </div>
                        </article>

                        <article class="login-benefit-item">
                            <span class="login-benefit-icon is-users">
                                <img src="{{ asset('images/login/benefit-users-a.svg') }}" alt="" aria-hidden="true">
                            </span>
                            <div>
                                <h2>Akses Pengguna Terkelola</h2>
                                <p>Setiap transaksi terhubung dengan akun dan pengguna yang mencatatnya sehingga lebih mudah diperiksa kembali.</p>
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
                            <h1>Masuk</h1>
                            <p>Kelola catatan dan transaksi keuangan Anda.</p>
                        </header>

                        @if ($demoCredentials)
                            <div class="login-demo-note">
                                <strong>Demo lokal</strong>
                                <span>{{ $demoCredentials['email'] }} / {{ $demoCredentials['password'] }}</span>
                            </div>
                        @endif

                        <form action="{{ route('tenant.login.store') }}" method="post" class="login-form">
                            @csrf

                            <label class="login-field" for="email">
                                <span class="login-label">Email</span>
                                <span class="login-input-wrap">
                                    <img class="login-input-icon" src="{{ asset('images/login/icon-email.svg') }}" alt="" aria-hidden="true">
                                    <input id="email" name="email" type="email" class="login-input" value="{{ old('email') }}" placeholder="nama@email.com" required>
                                </span>
                                @error('email')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="login-field" for="password">
                                <span class="login-field-head">
                                    <span class="login-label">Password</span>
                                    <a href="{{ route('tenant.password.request') }}" class="login-inline-link">Lupa password?</a>
                                </span>
                                <span class="login-input-wrap">
                                    <img class="login-input-icon icon-lock" src="{{ asset('images/login/icon-lock.svg') }}" alt="" aria-hidden="true">
                                    <input id="password" name="password" type="password" class="login-input" placeholder="Masukkan password Anda" required data-password-input>
                                    <button type="button" class="login-password-toggle" data-password-toggle aria-label="Tampilkan atau sembunyikan password">
                                        <img class="login-input-icon icon-eye" src="{{ asset('images/login/icon-eye.svg') }}" alt="" aria-hidden="true">
                                    </button>
                                </span>
                                @error('password')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="login-remember">
                                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                                <span>Ingat saya</span>
                            </label>

                            <button type="submit" class="login-submit">
                                <span>Masuk</span>
                                <img width="12" height="12" src="{{ asset('images/login/icon-arrow-right.svg') }}" alt="" aria-hidden="true">
                            </button>
                        </form>

                        <p class="login-register-link">
                            Belum punya akun?
                            <a href="{{ $supportWhatsappUrl }}" target="_blank" rel="noreferrer">Daftar sekarang</a>
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
