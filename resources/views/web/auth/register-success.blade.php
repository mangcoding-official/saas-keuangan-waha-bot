@extends('layouts.base', ['bodyClass' => 'page-home page-register-success'])

@section('body')
<div class="landing-page register-success-page">
    @include('web.partials.landing-header')

    <main class="register-success-main">
        <div class="register-success-glow register-success-glow-top" aria-hidden="true"></div>
        <div class="register-success-glow register-success-glow-bottom" aria-hidden="true"></div>

        <aside class="register-success-floating register-success-floating-left" aria-hidden="true">
            <div class="register-success-floating-icon is-green">
                <img src="{{ asset('images/register-success/shield.svg') }}" alt="">
            </div>
            <div>
                <small>Terverifikasi</small>
                <strong>Sistem Aman 100%</strong>
            </div>
        </aside>

        <aside class="register-success-floating register-success-floating-right" aria-hidden="true">
            <div class="register-success-floating-icon is-brown">
                <img src="{{ asset('images/register-success/lock.svg') }}" alt="">
            </div>
            <div>
                <small>Data Aman</small>
                <strong>Enkripsi End-to-End</strong>
            </div>
        </aside>

        <section class="register-success-shell">
            <header class="register-success-header">
                <span class="register-success-badge">
                    <img src="{{ asset('images/register-success/success-badge.svg') }}" alt="" aria-hidden="true">
                </span>
                <h1>Akun berhasil dibuat</h1>
                <p>Langkah terakhir! Silakan aktifkan akun Anda melalui WhatsApp untuk menjamin keamanan transaksi.</p>
            </header>

            <article class="register-success-card">
                <p class="register-success-card-label">Kode Aktivasi Anda</p>

                <div class="register-success-code-box">
                    <strong>{{ $page['activation_code'] }}</strong>
                    <button type="button" class="register-success-copy" data-copy-text="{{ $page['activation_code'] }}" aria-label="Salin kode aktivasi">
                        <img src="{{ asset('images/register-success/copy.svg') }}" alt="" aria-hidden="true">
                    </button>
                </div>

                <div class="register-success-timer" data-countdown data-expires-at="{{ $page['activation_expires_at'] }}">
                    <img src="{{ asset('images/register-success/clock.svg') }}" alt="" aria-hidden="true">
                    <strong data-countdown-label>Berakhir dalam 15:00</strong>
                </div>

                <div class="register-success-divider"></div>

                <div class="register-success-instruction">
                    <p>Kirim pesan berikut dari nomor yang terdaftar:</p>

                    <div class="register-success-command">
                        <span>{{ $page['activation_command'] }}</span>
                    </div>

                    <p>
                        Kirim ke nomor WhatsApp <strong>{{ $page['bot_name'] }}</strong>
                        @if (! empty($page['bot_display_number']))
                            <span class="register-success-number">{{ $page['bot_display_number'] }}</span>
                        @endif
                    </p>

                    <p class="register-success-note">{{ $page['status_note'] }}</p>
                </div>
            </article>

            <div class="register-success-actions">
                @if (! empty($page['whatsapp_url']))
                    <a href="{{ $page['whatsapp_url'] }}" class="register-success-button is-whatsapp" target="_blank" rel="noreferrer">
                        <img src="{{ asset('images/register-success/whatsapp.svg') }}" alt="" aria-hidden="true">
                        <span>Buka WhatsApp</span>
                    </a>
                @else
                    <span class="register-success-button is-whatsapp is-disabled">
                        <img src="{{ asset('images/register-success/whatsapp.svg') }}" alt="" aria-hidden="true">
                        <span>WhatsApp bot belum tersedia</span>
                    </span>
                @endif

                <a href="{{ route('tenant.login.create') }}" class="register-success-button is-primary">Masuk ke Dashboard</a>

                <form action="{{ route('tenant.register.success.regenerate') }}" method="post">
                    @csrf
                    <button type="submit" class="register-success-link">Buat kode baru</button>
                </form>
            </div>

            <footer class="register-success-footer">
                <p>Butuh bantuan? Hubungi <a href="{{ route('home') }}#footer">Pusat Bantuan</a> kami.</p>
            </footer>
        </section>
    </main>

    @include('web.partials.landing-footer')
</div>
@endsection
