@props(['vin'])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
@endphp

@if ($parts)
    {{-- VIN copies on click; compact phone-readback control; tooltip teleports above workspace tabs. --}}
    <span
        {{ $attributes->class(['d-inline-flex align-items-center gap-1']) }}
        x-data="arkVinDisplay(@js($parts['vin']))"
        data-vin-display
    >
        <button
            type="button"
            x-ref="trigger"
            class="btn btn-link p-0 font-monospace text-reset ops-vin-copyable"
            @click="copy()"
            @focus="showTooltip()"
            @blur="hideTooltip($event)"
            title="Click to copy VIN"
            :aria-label="'Copy VIN ' + vin"
        >
            @if ($parts['prefix'] !== '')
                <span class="text-secondary">{{ $parts['prefix'] }}</span>
            @endif
            <span class="fw-bold">{{ $parts['suffix'] }}</span>
        </button>
        <button
            type="button"
            class="btn btn-sm btn-ghost-secondary btn-icon p-0 ops-vin-readback-btn"
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
            <x-tabler.icon name="phone" />
        </button>
        <span class="badge bg-green-lt" x-show="copied" x-cloak role="status">Copied</span>
        <template x-teleport="body">
            <span
                x-ref="tooltip"
                x-show="tooltipOpen"
                x-cloak
                :style="tooltipStyle"
                class="card card-sm shadow position-fixed p-2 ops-vin-tooltip-layer"
                style="width: max-content; max-width: min(22rem, calc(100vw - 1rem))"
                role="tooltip"
                data-vin-phonetic-readback
                @mouseenter="showTooltip()"
                @mouseleave="hideTooltip($event)"
            >
                <span class="subheader d-block mb-1">Phone readback</span>
                @foreach (\App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']) as $section)
                    <span class="d-block mb-1">
                        <span @class(['d-block small', 'fw-bold' => $section['is_serial'], 'text-secondary' => ! $section['is_serial']])>
                            {{ $section['label'] }}
                            <span class="text-secondary fw-normal">{{ $section['meta'] }}</span>
                        </span>
                        <span class="d-flex flex-wrap gap-1">
                            @foreach ($section['chars'] as $index => $char)
                                <span @class(['d-inline-flex flex-column align-items-center border rounded px-1', 'border-primary' => $section['is_serial']])>
                                    <span class="font-monospace fw-bold">{{ $char }}</span>
                                    <span class="small text-secondary">{{ $section['phonetic_chars'][$index] ?? $char }}</span>
                                </span>
                            @endforeach
                        </span>
                    </span>
                @endforeach
            </span>
        </template>
    </span>
@endif
