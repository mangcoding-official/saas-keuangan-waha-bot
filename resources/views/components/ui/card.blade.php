@props([
    'title' => null,
    'description' => null,
])

<article {{ $attributes->merge(['class' => 'panel']) }}>
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

    {{ $slot }}
</article>
