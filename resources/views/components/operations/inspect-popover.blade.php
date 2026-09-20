@props([
    'title' => null,
    'items' => [],
    'footer' => null,
    'align' => 'start',
    'above' => false,
])

@php
    $hasItems = is_array($items) && count($items) > 0;
@endphp

<span
    x-data="arkInspectPopover()"
    {{ $attributes->class([
        'ops-inspect-popover',
        'ops-inspect-popover--align-'.$align,
        'ops-inspect-popover--above' => $above,
    ]) }}
    :class="{ 'ops-inspect-popover--open': open }"
    data-line-card-ignore
    @click="toggle($event)"
    @click.outside="close()"
    @keydown.escape.window="close()"
>
    {{ $slot }}

    @if ($hasItems)
        <span class="ops-inspect-popover__backdrop" @click="close()"></span>
        <span class="ops-inspect-popover__panel" role="tooltip">
            <button type="button" class="ops-inspect-popover__close" @click.stop="close()">Close</button>
            @if (filled($title))
                <p class="ops-inspect-popover__title">{{ $title }}</p>
            @endif
            <ul class="ops-inspect-popover__list">
                @foreach ($items as $item)
                    <li class="ops-inspect-popover__row">
                        <span class="ops-inspect-popover__label">{{ $item['label'] }}</span>
                        <span class="ops-inspect-popover__value">{{ $item['detail'] ?? $item['value'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>
            @if (filled($footer))
                <p class="ops-inspect-popover__footer">{{ $footer }}</p>
            @endif
        </span>
    @endif
</span>
