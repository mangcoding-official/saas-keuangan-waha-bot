<aside class="tenant-sidebar" data-sidebar>
    <div class="tenant-sidebar-brand">
        <h2 class="tenant-sidebar-name">{{ $authUser->tenant->name }}</h2>
        <p class="tenant-sidebar-copy">Selamat datang, {{ $authUser->name }}</p>
    </div>

    @php
        $profileInitials = collect(preg_split('/\s+/', trim($authUser->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $segment): string => strtoupper(substr($segment, 0, 1)))
            ->implode('');
        $profileInitials = $profileInitials !== '' ? $profileInitials : 'U';
    @endphp

    <nav class="tenant-sidebar-nav">
        @foreach ($navigation as $item)
            @php
                $iconKey = $item['key'] ?? 'overview';
            @endphp
            <a href="{{ route($item['route']) }}" class="tenant-sidebar-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}" data-nav-key="{{ $item['key'] ?? '' }}">
                <span class="tenant-sidebar-link-icon is-{{ $item['key'] ?? 'default' }}" aria-hidden="true">
                    @if ($iconKey === 'profile')
                        <span class="tenant-sidebar-link-icon-fallback">{{ $profileInitials }}</span>
                    @else
                        <img src="{{ asset('images/figma/accounts/'.$iconKey.'.svg') }}" alt="">
                    @endif
                </span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <form action="{{ route('tenant.logout') }}" method="post" class="tenant-sidebar-footer">
        @csrf
        <button class="tenant-sidebar-logout" type="submit">
            <span class="tenant-sidebar-logout-icon" aria-hidden="true">
                <img src="{{ asset('images/figma/accounts/logout.svg') }}" alt="">
            </span>
            <span>Keluar</span>
        </button>
    </form>
</aside>
