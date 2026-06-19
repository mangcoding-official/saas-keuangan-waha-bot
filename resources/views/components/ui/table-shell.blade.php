@props([
    'title' => null,
    'description' => null,
])

<div class="panel">
    @if ($title || $description)
        <div class="panel-head">
            <div>
                @if ($title)
                    <h2 class="panel-title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="panel-copy">{{ $description }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="table-shell">
        <table>
            {{ $slot }}
        </table>
    </div>
</div>
