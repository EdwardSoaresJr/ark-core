@props(['vin'])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
@endphp

@if ($parts)
    {{-- Same arkVinDisplay behavior as x-operations.vin-display: copy on click, phone readback on hover or focus. --}}
    <span
        {{ $attributes->class(['d-inline-flex align-items-center gap-1']) }}
        x-data="arkVinDisplay(@js($parts['vin']))"
        @mouseenter="showTooltip()"
        @mouseleave="hideTooltip()"
        @focusin="showTooltip()"
        @focusout="hideTooltip($event)"
        data-vin-display
    >
        <button type="button" x-ref="trigger" class="btn btn-link p-0 font-monospace text-reset text-decoration-none" @click="copy()" :aria-label="'Copy VIN ' + vin">
            @if ($parts['prefix'] !== '')
                <span class="text-secondary">{{ $parts['prefix'] }}</span>
            @endif
            <span class="fw-bold">{{ $parts['suffix'] }}</span>
        </button>
        <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon p-0" @click.stop="copy()" :aria-label="'Copy VIN ' + vin" title="Copy VIN">
            <x-tabler.icon name="file-text" />
        </button>
        <span class="badge bg-green-lt" x-show="copied" x-cloak>Copied</span>
        <span x-ref="tooltip" x-show="tooltipOpen" x-cloak :style="tooltipStyle" class="ark-legacy ops-vin-tooltip ops-vin-tooltip--fixed" role="tooltip">
            @include('operations.partials._vin-phonetic-tooltip', [
                'sections' => \App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']),
            ])
        </span>
    </span>
@endif
