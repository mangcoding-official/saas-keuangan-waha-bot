@php
    $searchLabel = $toolbar['search_label'] ?? 'Cari data tenant';
    $searchPlaceholder = $toolbar['search_placeholder'] ?? 'Cari transaksi...';
    $searchAction = $toolbar['search_action'] ?? url()->current();
    $searchName = $toolbar['search_name'] ?? 'search';
    $searchValue = $toolbar['search_value'] ?? '';
    $secondaryAction = $toolbar['secondary_action'] ?? null;
    $primaryAction = $toolbar['primary_action'] ?? null;
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
                <div class="tenant-topbar-profile">
                    <div class="tenant-topbar-profile-copy">
                        <strong>{{ $authUser->name }}</strong>
                        <span>{{ ucfirst($authUser->role->value) }}</span>
                    </div>
                    <span class="tenant-topbar-avatar">{{ strtoupper(substr($authUser->name, 0, 1)) }}</span>
                </div>
            @endif

            <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>
        </div>
    </div>
</header>
