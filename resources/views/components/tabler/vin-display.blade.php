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
        <span
            x-ref="tooltip"
            x-show="tooltipOpen"
            x-cloak
            :style="tooltipStyle"
            class="card card-sm shadow position-fixed p-2"
            style="z-index: 1080; width: max-content; max-width: min(22rem, calc(100vw - 1rem))"
            role="tooltip"
            data-vin-phonetic-readback
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
    </span>
@endif
