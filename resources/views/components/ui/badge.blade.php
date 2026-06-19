@props([
    'tone' => 'neutral',
])

<span {{ $attributes->merge(['class' => 'badge', 'data-tone' => $tone]) }}>
    {{ $slot }}
</span>
