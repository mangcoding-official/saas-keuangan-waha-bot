@php
    $authUser = auth('web')->user();
    $authUserInitials = $authUser
        ? collect(preg_split('/\s+/', trim($authUser->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $segment): string => strtoupper(substr($segment, 0, 1)))
            ->implode('')
        : '';
@endphp

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
            @if ($authUser)
                <a href="{{ route('tenant.dashboard') }}" class="landing-button landing-button-secondary">Dashboard</a>

                <div class="tenant-topbar-account" data-topbar-account>
                    <button
                        type="button"
                        class="tenant-topbar-profile"
                        data-topbar-account-trigger
                        aria-expanded="false"
                        aria-haspopup="dialog"
                        aria-label="Buka menu akun"
                    >
                        <span class="tenant-topbar-avatar">{{ strtoupper(substr($authUser->name, 0, 1)) }}</span>
                    </button>

                    <div class="tenant-topbar-account-popover" data-topbar-account-popover hidden>
                        <div class="tenant-topbar-account-head">
                            <p>{{ $authUser->email ?: $authUser->tenant->name }}</p>
                            <button type="button" class="tenant-topbar-account-close" data-topbar-account-close aria-label="Tutup panel akun">&times;</button>
                        </div>

                        <div class="tenant-topbar-account-summary">
                            <span class="tenant-topbar-account-avatar">{{ $authUserInitials !== '' ? $authUserInitials : 'U' }}</span>
                            <strong>Hai, {{ str($authUser->name)->before(' ') }}!</strong>
                            <span>{{ $authUser->tenant->name }} • {{ ucfirst($authUser->role->value) }}</span>
                        </div>

                        <a href="{{ route('tenant.profile.show') }}" class="tenant-topbar-account-primary">
                            Lihat Profil Saya
                        </a>

                        <div class="tenant-topbar-account-actions">
                            <a href="{{ route('tenant.dashboard') }}" class="tenant-topbar-account-action">
                                <span class="tenant-topbar-account-action-icon" aria-hidden="true">+</span>
                                <span>Buka Dashboard</span>
                            </a>

                            <form action="{{ route('tenant.logout') }}" method="post" class="tenant-topbar-account-action-form">
                                @csrf
                                <button type="submit" class="tenant-topbar-account-action is-logout">
                                    <span class="tenant-topbar-account-action-icon" aria-hidden="true">&#8594;</span>
                                    <span>Keluar</span>
                                </button>
                            </form>
                        </div>

                        <div class="tenant-topbar-account-footer">
                            <span>{{ $authUser->tenant->timezone }}</span>
                            <span>&bull;</span>
                            <span>Workspace aktif</span>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('tenant.login.create') }}" class="landing-button landing-button-secondary">Masuk</a>
                <a href="{{ $supportWhatsappUrl }}" class="landing-button landing-button-primary" target="_blank" rel="noreferrer">
                    <span>Daftar Sekarang</span>
                    <img src="{{ asset('images/landing/arrow-right.svg') }}" alt="" aria-hidden="true">
                </a>
            @endif
        </div>
    </div>
</header>
