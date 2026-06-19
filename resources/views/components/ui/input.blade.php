@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'disabled' => false,
    'help' => null,
    'required' => false,
    'full' => false,
])

<label class="field {{ $full ? 'field-full' : '' }}" for="{{ $name }}">
    @if ($label)
        <span class="field-label">{{ $label }}</span>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        @disabled($disabled)
        @required($required)
        {{ $attributes->merge(['class' => 'input-control']) }}
    >

    @if ($help)
        <p class="field-help">{{ $help }}</p>
    @endif

    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</label>
