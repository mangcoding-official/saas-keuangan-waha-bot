@php
    $searchLabel = $toolbar['search_label'] ?? 'Cari data tenant';
    $searchPlaceholder = $toolbar['search_placeholder'] ?? 'Cari transaksi...';
    $searchAction = $toolbar['search_action'] ?? url()->current();
    $searchName = $toolbar['search_name'] ?? 'search';
    $searchValue = $toolbar['search_value'] ?? '';
    $secondaryAction = $toolbar['secondary_action'] ?? null;
    $primaryAction = $toolbar['primary_action'] ?? null;
    $authUserInitials = isset($authUser)
        ? collect(preg_split('/\s+/', trim($authUser->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $segment): string => strtoupper(substr($segment, 0, 1)))
            ->implode('')
        : '';
@endphp

<header class="tenant-topbar">
    @if (!empty($page['title']) || !empty($page['description']))
        <div class="tenant-topbar-meta">
            @if (!empty($page['title']))
                <h1 class="tenant-topbar-title">{{ $page['title'] }}</h1>
            @endif
            @if (!empty($page['description']))
                <p class="tenant-topbar-copy">{{ $page['description'] }}</p>
            @endif
        </div>
    @endif

    <div class="tenant-topbar-toolbar">
        {{-- <form method="get" action="{{ $searchAction }}" class="tenant-topbar-search" aria-label="{{ $searchLabel }}">
            <input type="text" name="{{ $searchName }}" value="{{ $searchValue }}" class="tenant-topbar-search-input" placeholder="{{ $searchPlaceholder }}">
        </form> --}}

        <div class="tenant-topbar-actions">
            @if ($secondaryAction)
                <x-ui.button :href="$secondaryAction['href']" :variant="$secondaryAction['variant']">{{ $secondaryAction['label'] }}</x-ui.button>
            @endif

            @if ($primaryAction)
                <x-ui.button :href="$primaryAction['href']" :variant="$primaryAction['variant']">{{ $primaryAction['label'] }}</x-ui.button>
            @endif

            @if (isset($authUser))
                <div class="tenant-topbar-account" data-topbar-account>
                    <button
                        type="button"
                        class="tenant-topbar-profile"
                        data-topbar-account-trigger
                        aria-expanded="false"
                        aria-haspopup="dialog"
                    >
                        <div class="tenant-topbar-profile-copy">
                            <strong>{{ $authUser->name }}</strong>
                            <span>{{ ucfirst($authUser->role->value) }}</span>
                        </div>
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
            @endif

            <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>
        </div>
    </div>
</header>
