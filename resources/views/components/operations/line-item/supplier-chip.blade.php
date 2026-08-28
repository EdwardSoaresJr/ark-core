@props([
    'label',
])

<span {{ $attributes->class(['ops-chip', 'ops-chip--supplier']) }}>
    {{ $label }}
</span>
