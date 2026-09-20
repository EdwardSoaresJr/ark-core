@props(['vin', 'class' => ''])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
@endphp

@if ($parts)
    <span
        {{ $attributes->merge(['class' => 'ops-vin-display '.$class]) }}
        x-data="arkVinDisplay(@js($parts['vin']))"
        @mouseenter="showTooltip()"
        @mouseleave="hideTooltip()"
        @focusin="showTooltip()"
        @focusout="hideTooltip($event)"
    >
        <button
            type="button"
            x-ref="trigger"
            class="ops-vin-trigger"
            @click="copy()"
            :aria-label="'Copy VIN ' + vin"
        >
            @if ($parts['prefix'] !== '')
                <span class="ops-vin-prefix">{{ $parts['prefix'] }}</span>
            @endif
            <span class="ops-vin-suffix">{{ $parts['suffix'] }}</span>
        </button>
        <button
            type="button"
            class="ops-vin-copy-btn"
            @click.stop="copy()"
            :aria-label="'Copy VIN ' + vin"
            title="Copy VIN"
        >
            <x-operations.vin-copy-icon />
        </button>
        <span class="ops-vin-copied" x-show="copied" x-cloak>Copied</span>
        <span
            x-ref="tooltip"
            x-show="tooltipOpen"
            x-cloak
            :style="tooltipStyle"
            class="ops-vin-tooltip ops-vin-tooltip--fixed"
            role="tooltip"
        >
            @include('operations.partials._vin-phonetic-tooltip', [
                'sections' => \App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']),
            ])
        </span>
    </span>
@endif
