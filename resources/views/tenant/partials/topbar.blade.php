@php
    $searchLabel = $toolbar['search_label'] ?? 'Cari data tenant';
    $searchPlaceholder = $toolbar['search_placeholder'] ?? 'Cari transaksi...';
    $secondaryAction = $toolbar['secondary_action'] ?? null;
    $primaryAction = $toolbar['primary_action'] ?? null;
@endphp

<header class="tenant-topbar">
    <div class="tenant-topbar-meta">
        @if (!empty($page['title']))
            <h1 class="tenant-topbar-title">{{ $page['title'] }}</h1>
        @endif
        @if (!empty($page['description']))
            <p class="tenant-topbar-copy">{{ $page['description'] }}</p>
        @endif
    </div>

    <div class="tenant-topbar-toolbar">
        <label class="tenant-topbar-search" aria-label="{{ $searchLabel }}">
            <input type="text" class="tenant-topbar-search-input" placeholder="{{ $searchPlaceholder }}">
        </label>

        <div class="tenant-topbar-actions">
            @if ($secondaryAction)
                <x-ui.button :href="$secondaryAction['href']" :variant="$secondaryAction['variant']">{{ $secondaryAction['label'] }}</x-ui.button>
            @endif

            @if ($primaryAction)
                <x-ui.button :href="$primaryAction['href']" :variant="$primaryAction['variant']">{{ $primaryAction['label'] }}</x-ui.button>
            @endif

            <button class="button button-secondary sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>
        </div>
    </div>
</header>
