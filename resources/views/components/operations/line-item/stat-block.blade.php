@props([
    'label',
    'value',
])

<div {{ $attributes->class(['ops-line-stat']) }}>
    <span class="ops-line-stat__label">{{ $label }}</span>
    <span class="ops-line-stat__value">{{ $value }}</span>
</div>
