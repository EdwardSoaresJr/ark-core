@props([
    'count',
    'inline' => false,
])

@if ((int) $count > 0)
    <span {{ $attributes->class([
        'ops-pressure-count',
        'ops-pressure-count--inline' => $inline,
    ]) }}>
        @if ($inline)
            ({{ (int) $count }})
        @else
            {{ (int) $count }}
        @endif
    </span>
@endif
