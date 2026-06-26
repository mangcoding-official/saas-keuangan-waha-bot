<header class="landing-nav">
    <div class="header-wrapper">
        <a href="{{ route('home') }}" class="landing-brand" aria-label="Aplikasi Keuangan MACAU Bot">
            <span class="landing-brand-mark">
                <img src="{{ asset('images/macau-bot.svg') }}" alt="macau - aplikasi keuangan" aria-hidden="true">
            </span>
            <span class="landing-brand-text">Macau</span>
        </a>

        <nav class="landing-menu" aria-label="Navigasi utama">
            <a href="{{ route('home') }}#fitur">Fitur</a>
            <a href="{{ route('home') }}#cara-kerja">Cara Kerja</a>
            <a href="{{ route('home') }}#footer">FAQ</a>
        </nav>

        <div class="landing-nav-actions">
            <a href="{{ route('tenant.login.create') }}" class="landing-button landing-button-secondary">Masuk</a>
            <a href="{{ route('tenant.register.create') }}" class="landing-button landing-button-primary">
                <span>Daftar Sekarang</span>
                <img src="{{ asset('images/landing/arrow-right.svg') }}" alt="" aria-hidden="true">
            </a>
        </div>
    </div>
</header>
