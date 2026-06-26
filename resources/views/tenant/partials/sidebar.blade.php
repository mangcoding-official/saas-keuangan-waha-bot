<aside class="tenant-sidebar" data-sidebar>
    <div class="tenant-sidebar-brand">
        <h2 class="tenant-sidebar-name">{{ $authUser->tenant->name }}</h2>
        <p class="tenant-sidebar-copy">Selamat datang, {{ $authUser->name }}</p>
    </div>

    <nav class="tenant-sidebar-nav">
        @foreach ($navigation as $item)
            <a href="{{ route($item['route']) }}" class="tenant-sidebar-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                <span class="tenant-sidebar-link-icon" aria-hidden="true"></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <form action="{{ route('tenant.logout') }}" method="post" class="tenant-sidebar-footer">
        @csrf
        <button class="tenant-sidebar-logout" type="submit">Keluar</button>
    </form>
</aside>
