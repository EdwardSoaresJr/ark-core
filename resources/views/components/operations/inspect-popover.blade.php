@props([
    'title' => null,
    'items' => [],
    'footer' => null,
    'align' => 'start',
    'above' => false,
    'block' => false,
])

@php
    $hasItems = is_array($items) && count($items) > 0;
@endphp

{{-- Hover opens the panel as a dropdown; on touch screens a tap opens it as a bottom sheet (arkInspectPopover). --}}
<span
    x-data="arkInspectPopover({ above: @js((bool) $above), align: @js($align) })"
    {{ $attributes->class(['position-relative', 'd-inline-flex mw-100' => ! $block]) }}
    data-inspect-popover
    data-line-card-ignore
    @mouseenter="enter()"
    @mouseleave="leave()"
    @click="toggle($event)"
    @click.outside="close()"
    @keydown.escape.window="close()"
>
    {{ $slot }}

    @if ($hasItems)
        <span x-show="touchMode && open" x-cloak class="offcanvas-backdrop fade show" @click.stop="close()"></span>
        <span
            x-cloak
            :class="panelClasses()"
            :style="touchMode ? 'max-height: 70dvh' : 'min-width: 11.5rem; max-width: 16rem'"
            role="tooltip"
            data-inspect-popover-panel
        >
            <span class="d-flex align-items-start gap-2">
                @if (filled($title))
                    <span class="subheader flex-fill mb-2">{{ $title }}</span>
                @endif
                <button type="button" class="btn-close ms-auto" x-show="touchMode" x-cloak aria-label="Close" @click.stop="close()"></button>
            </span>
            <span class="d-block">
                @foreach ($items as $item)
                    <span class="d-flex align-items-baseline justify-content-between gap-3 small">
                        <span class="text-secondary">{{ $item['label'] }}</span>
                        <span class="fw-bold text-end ark-money">{{ $item['detail'] ?? $item['value'] ?? '' }}</span>
                    </span>
                @endforeach
            </span>
            @if (filled($footer))
                <span class="d-block small fw-medium text-secondary border-top mt-2 pt-2">{{ $footer }}</span>
            @endif
        </span>
    @endif
</span>
