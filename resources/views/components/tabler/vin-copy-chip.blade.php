@props(['vin'])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
    $panelId = 'vin-chip-readback-'.uniqid();
@endphp

@if ($parts)
    {{-- The VIN is the only control: click/tap copies it; hover, focus or a touch tap shows the per-character readback. --}}
    <span
        {{ $attributes->class(['d-inline-flex align-items-center']) }}
        x-data="arkVinDisplay(@js($parts['vin']), { inline: true })"
        title=""
        data-vin-chip
    >
        <button
            type="button"
            x-ref="trigger"
            class="btn btn-link p-0 border-0 font-monospace fw-bold text-reset ops-vin-copyable"
            @pointerdown="pointerType = $event.pointerType"
            @click="activate()"
            @mouseenter="onReadbackHoverEnter()"
            @mouseleave="onReadbackHoverLeave()"
            @focus="onReadbackHoverEnter()"
            @blur="onReadbackHoverLeave()"
            :aria-describedby="readbackOpen ? '{{ $panelId }}' : null"
            :aria-expanded="readbackOpen ? 'true' : 'false'"
            aria-haspopup="dialog"
            aria-label="VIN {{ $parts['vin'] }}. Copy to clipboard; phonetic spelling on hover or focus."
            data-vin-copy
        >{{ $parts['vin'] }}</button>
        <span class="visually-hidden" role="status" aria-live="polite" x-text="copied ? 'VIN copied' : (copyFailed ? 'VIN could not be copied' : '')"></span>

        <template x-teleport="body">
            <div x-show="readbackOpen" x-cloak data-vin-readback-layer>
                <div
                    id="{{ $panelId }}"
                    x-ref="panel"
                    class="card card-sm shadow position-fixed p-2 ops-vin-tooltip-layer ops-vin-readback-popover"
                    :style="tooltipStyle"
                    role="dialog"
                    aria-label="VIN phonetic spelling"
                    tabindex="-1"
                    data-vin-phonetic-readback
                    @mouseenter="onReadbackHoverEnter()"
                    @mouseleave="onReadbackHoverLeave()"
                    @keydown.escape.stop="closeReadback()"
                >
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <span class="subheader mb-0 font-monospace">{{ $parts['vin'] }}</span>
                        <button type="button" class="btn-close" aria-label="Close VIN readback" @click="closeReadback()"></button>
                    </div>
                    <div class="small mb-1" data-vin-copy-status>
                        <span class="text-green" x-show="copied" x-cloak>VIN copied</span>
                        <span class="text-danger" x-show="copyFailed" x-cloak>Could not copy. Select the VIN to copy it by hand.</span>
                        <span class="text-secondary" x-show="! copied && ! copyFailed">Click the VIN to copy</span>
                    </div>
                    <ol class="list-unstyled small mb-0 row row-cols-2 row-cols-sm-3 g-1" data-vin-phonetic-list>
                        @foreach ($parts['chars'] as $char)
                            <li class="col text-nowrap"><span class="font-monospace fw-bold">{{ $char }}</span> — {{ \App\Ark\Operations\Vehicles\VinDisplay::phonetic($char) }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </template>
    </span>
@endif
