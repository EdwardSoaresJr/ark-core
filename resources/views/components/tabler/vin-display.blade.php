@props(['vin'])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
    $sections = \App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']);
    $readbackPanelId = 'vin-readback-'.uniqid();
@endphp

@if ($parts)
    {{-- VIN click copies; abc icon opens character readback (desktop popover / mobile sheet). --}}
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
            x-ref="readback"
            class="btn btn-sm btn-ghost-secondary btn-icon p-0 ops-vin-readback-btn"
            @click.stop="toggleReadback()"
            :aria-expanded="readbackOpen ? 'true' : 'false'"
            aria-controls="{{ $readbackPanelId }}"
            aria-haspopup="dialog"
            aria-label="VIN readback"
            title="VIN readback"
            data-vin-readback-trigger
        >
            <x-tabler.icon name="abc" />
        </button>
        <span class="badge bg-green-lt" x-show="copied" x-cloak role="status" aria-live="polite">VIN copied</span>

        <template x-teleport="body">
            <div x-show="readbackOpen" x-cloak data-vin-readback-layer>
                <div
                    x-ref="backdrop"
                    x-show="sheetMode"
                    x-cloak
                    class="offcanvas-backdrop fade show ops-vin-readback-backdrop"
                    @click="closeReadback()"
                ></div>
                <div
                    id="{{ $readbackPanelId }}"
                    x-ref="panel"
                    :class="sheetMode
                        ? 'offcanvas offcanvas-bottom h-auto show ops-vin-readback-sheet'
                        : 'card card-sm shadow position-fixed p-2 ops-vin-tooltip-layer ops-vin-readback-popover'"
                    :style="sheetMode ? '' : tooltipStyle"
                    role="dialog"
                    aria-modal="true"
                    aria-label="VIN readback"
                    tabindex="-1"
                    data-vin-phonetic-readback
                    @keydown.escape.stop="closeReadback()"
                >
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <span class="subheader mb-0">VIN readback</span>
                        <button type="button" class="btn-close" aria-label="Close VIN readback" @click="closeReadback()"></button>
                    </div>
                    @foreach ($sections as $section)
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
                </div>
            </div>
        </template>
    </span>
@endif
