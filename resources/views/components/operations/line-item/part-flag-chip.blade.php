@props([
    'label',
    'variant' => 'save',
])

<span {{ $attributes->class([
    'ops-chip',
    'ops-chip--flag',
    'ops-chip--flag-'.$variant,
]) }}>
    {{ $label }}
</span>
