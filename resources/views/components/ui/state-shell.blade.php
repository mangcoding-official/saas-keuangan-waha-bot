@props([
    'title',
    'description',
    'tone' => 'neutral',
])

<div class="state-shell" data-tone="{{ $tone }}">
    <x-ui.badge :tone="$tone">{{ strtoupper($tone) }}</x-ui.badge>
    <h3>{{ $title }}</h3>
    <p>{{ $description }}</p>
</div>
