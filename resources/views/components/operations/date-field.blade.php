@props([
    'id' => null,
    'name',
    'label' => null,
    'value' => '',
    'min' => null,
])

@php
    $inputId = $id ?? $name;
@endphp

<div {{ $attributes->class(['ops-date-field', 'ops-date-field--compact' => blank($label)]) }}>
    @if (filled($label))
        <label for="{{ $inputId }}" class="ops-index-field-label">{{ $label }}</label>
    @endif
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="date"
        value="{{ $value }}"
        @if (filled($min)) min="{{ $min }}" @endif
        class="ops-index-field ops-date-input"
    >
</div>
