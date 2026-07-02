@extends('layouts.base', ['bodyClass' => 'page-home'])

@section('body')
@php
    $authUser = auth('web')->user();
@endphp
<div class="landing-page">
    @include('web.partials.landing-header')

    <main class="landing-main">
        <section class="landing-hero">
            <img class="landing-hero-glow" src="{{ asset('images/landing/hero-glow.svg') }}" alt="" aria-hidden="true">

            <div class="landing-hero-copy">
                <span class="landing-chip">{{ strtoupper($page['eyebrow']) }}</span>

                <h1 class="landing-hero-title">
                    Catat keuangan lebih <span>praktis</span> lewat WhatsApp
                </h1>

                <p class="landing-hero-description">{{ $page['description'] }}</p>

                <div class="landing-hero-actions">
                    @unless ($authUser)
                        <a href="{{ $supportWhatsappUrl }}" class="landing-button landing-button-primary" target="_blank" rel="noreferrer">
                            <span>Daftar Sekarang</span>
                            <img src="{{ asset('images/landing/arrow-right.svg') }}" alt="" aria-hidden="true">
                        </a>
                    @endunless

                    <a href="{{ $authUser ? route('tenant.dashboard') : route('tenant.login.create') }}" class="landing-button landing-button-secondary">
                        Masuk ke Dashboard
                    </a>
                </div>

                {{-- <div class="landing-proof">
                    <div class="landing-proof-avatars" aria-hidden="true">
                        <img src="{{ asset('images/landing/avatar-1.png') }}" alt="">
                        <img src="{{ asset('images/landing/avatar-2.png') }}" alt="">
                        <img src="{{ asset('images/landing/avatar-3.png') }}" alt="">
                    </div>
                    <p>
                        <strong>{{ $page['heroStats']['value'] }}</strong>
                        {{ $page['heroStats']['label'] }}
                    </p>
                </div> --}}
            </div>

            <div class="landing-hero-visual" aria-label="Preview percakapan dan saldo">
                <article class="whatsapp-card">
                    <div class="whatsapp-card-head">
                        <img src="{{ asset('images/landing/avatar-bot.png') }}" alt="Avatar bot MACAU">
                        <div>
                            <strong>MACAU Bot</strong>
                            <span>Online</span>
                        </div>
                    </div>

                    <div class="whatsapp-message whatsapp-message-incoming">
                        {{ $page['heroMessages']['prompt'] }}
                    </div>

                    <div class="whatsapp-message whatsapp-message-outgoing">
                        {{ $page['heroMessages']['reply'] }}
                    </div>
                </article>

                <article class="balance-card">
                    <span>Total Saldo</span>
                    <strong>{{ $page['balance'] }}</strong>
                </article>
            </div>
        </section>

        <section id="fitur" class="landing-section landing-section-features">
            <div class="landing-section-heading">
                <h2>Fitur Unggulan MACAU Bot</h2>
                <p>Dirancang untuk memudahkan siapa saja mengelola keuangan tanpa perlu aplikasi yang rumit.</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card feature-card-wide feature-card-blue-soft">
                    <div class="feature-card-copy">
                        <span class="feature-icon-wrap">
                            <img class="feature-icon" src="{{ asset('images/landing/message-square.svg') }}" alt="" aria-hidden="true">
                        </span>
                        <h3>{{ $page['features'][0]['title'] }}</h3>
                        <p>{{ $page['features'][0]['description'] }}</p>
                    </div>
                    <img class="feature-card-art" src="{{ asset('images/landing/message-circle.svg') }}" alt="" aria-hidden="true">
                </article>

                <article class="feature-card feature-card-tall feature-card-blue-solid">
                    <div class="wrapper">
                        <img src="{{ asset('images/landing/chart-line.svg') }}" alt="" aria-hidden="true">
                        <h3>{{ $page['features'][1]['title'] }}</h3>
                        <p>{{ $page['features'][1]['description'] }}</p>
                    </div>
                </article>
            </div>

            <div class="feature-grid feature-grid__reverse">
                <article class="feature-card feature-card-plain">
                    <div class="wrapper">
                        <span class="feature-icon-wrap feature-icon-soft">
                            <img src="{{ asset('images/landing/users.svg') }}" alt="" aria-hidden="true">
                        </span>
                        <h3>{{ $page['features'][2]['title'] }}</h3>
                        <p>{{ $page['features'][2]['description'] }}</p>
                    </div>
                </article>

                <article class="feature-card feature-card-wide feature-card-green-soft">
                    <div class="feature-card-copy">
                        <h3>{{ $page['features'][3]['title'] }}</h3>
                        <p>{{ $page['features'][3]['description'] }}</p>
                    </div>

                    <div class="feature-history-list">
                        @foreach ($page['features'][3]['items'] as $item)
                        <div class="feature-history-item">
                            <div class="feature-history-label">
                                <img src="{{ asset('images/landing/'.($item['tone'] === 'expense' ? 'dot-red.svg' : 'dot-green.svg')) }}" alt="" aria-hidden="true">
                                <span>{{ $item['label'] }}</span>
                            </div>
                            <strong class="{{ $item['tone'] === 'expense' ? 'is-expense' : 'is-income' }}">{{ $item['amount'] }}</strong>
                        </div>
                        @endforeach
                    </div>
                </article>
            </div>
        </section>

        <div class="landing-section-steps">
            <section id="cara-kerja" class="landing-section">
                <div class="landing-section-heading">
                    <h2>Cara Kerja</h2>
                    <p>3 langkah mudah untuk mulai mengelola keuangan lebih baik.</p>
                </div>

                <div class="steps-flow" aria-hidden="true">
                    <img src="{{ asset('images/landing/line.svg') }}" alt="">
                </div>

                <div class="steps-grid">
                    @foreach ($page['steps'] as $step)
                    <article class="step-card">
                        <span class="step-badge">{{ $step['number'] }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['description'] }}</p>
                    </article>
                    @endforeach
                </div>
            </section>
        </div>

        <section id="cta" class="landing-cta-shell">
            <div class="landing-cta">
                <div class="landing-section-heading landing-section-heading-inverse">
                    <h2>Siap mencatat keuangan dengan cara baru?</h2>
                    <p>Bergabunglah dengan pengguna lain yang ingin pencatatan keuangan lebih cepat, rapi, dan mudah dipantau.</p>
                </div>

                <div class="landing-cta-actions">
                    <a
                        href="{{ $authUser ? route('tenant.dashboard') : $supportWhatsappUrl }}"
                        class="landing-button landing-button-light"
                        @unless ($authUser) target="_blank" rel="noreferrer" @endunless
                    >
                        {{ $authUser ? 'Buka Dashboard' : 'Daftar Gratis Sekarang' }}
                    </a>
                </div>

                <div class="landing-cta-benefits">
                    @foreach ($page['ctaBenefits'] as $benefit)
                    <span>
                        <img src="{{ asset('images/landing/check.svg') }}" alt="" aria-hidden="true">
                        {{ $benefit }}
                    </span>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    @include('web.partials.landing-footer')
</div>
@endsection
