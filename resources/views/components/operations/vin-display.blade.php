@props(['vin', 'class' => ''])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
@endphp

@if ($parts)
    <span
        {{ $attributes->merge(['class' => 'ops-vin-display '.$class]) }}
        x-data="arkVinDisplay(@js($parts['vin']))"
        data-vin-display
    >
        <button
            type="button"
            x-ref="trigger"
            class="ops-vin-trigger ops-vin-copyable"
            @click="copy()"
            @focus="showTooltip()"
            @blur="hideTooltip($event)"
            title="Click to copy VIN"
            :aria-label="'Copy VIN ' + vin"
        >
            @if ($parts['prefix'] !== '')
                <span class="ops-vin-prefix">{{ $parts['prefix'] }}</span>
            @endif
            <span class="ops-vin-suffix">{{ $parts['suffix'] }}</span>
        </button>
        <button
            type="button"
            class="ops-vin-readback-btn"
            @click.stop="toggleTooltip()"
            @mouseenter="showTooltip()"
            @mouseleave="hideTooltip($event)"
            @focus="showTooltip()"
            @blur="hideTooltip($event)"
            :aria-expanded="tooltipOpen ? 'true' : 'false'"
            aria-label="Phone readback for VIN"
            title="Phone readback"
            data-vin-readback-trigger
        >
            <span class="ops-vin-readback-glyph" aria-hidden="true">⌁</span>
        </button>
        <span class="ops-vin-copied" x-show="copied" x-cloak role="status">Copied</span>
        <template x-teleport="body">
            <span
                x-ref="tooltip"
                x-show="tooltipOpen"
                x-cloak
                :style="tooltipStyle"
                class="ops-vin-tooltip ops-vin-tooltip--fixed ops-vin-tooltip-layer"
                role="tooltip"
                data-vin-phonetic-readback
                @mouseenter="showTooltip()"
                @mouseleave="hideTooltip($event)"
            >
                @include('operations.partials._vin-phonetic-tooltip', [
                    'sections' => \App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']),
                ])
            </span>
        </template>
    </span>
@endif
