@props([
    'label',
    'tone' => 'neutral',
])

<span {{ $attributes->class([
    'ops-chip',
    'ops-chip--status',
    'ops-chip--'.$tone,
]) }}>
    {{ $label }}
</span>
