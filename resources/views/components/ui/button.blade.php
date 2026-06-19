@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button',
])

@php($classes = trim('button button-'.$variant.' '.($attributes->get('class') ?? '')))

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
