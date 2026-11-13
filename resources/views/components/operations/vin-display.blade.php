@props(['vin', 'class' => ''])

@php
    $parts = \App\Ark\Operations\Vehicles\VinDisplay::parts($vin);
    $sections = \App\Ark\Operations\Vehicles\VinDisplay::sections($parts['vin']);
    $readbackPanelId = 'vin-readback-ops-'.uniqid();
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
            x-ref="readback"
            class="ops-vin-readback-btn"
            @click.stop="toggleReadback()"
            :aria-expanded="readbackOpen ? 'true' : 'false'"
            aria-controls="{{ $readbackPanelId }}"
            aria-haspopup="dialog"
            aria-label="VIN readback"
            title="VIN readback"
            data-vin-readback-trigger
        >
            <span class="ops-vin-readback-glyph" aria-hidden="true">Abc</span>
        </button>
        <span class="ops-vin-copied" x-show="copied" x-cloak role="status" aria-live="polite">VIN copied</span>

        <template x-teleport="body">
            <div x-show="readbackOpen" x-cloak data-vin-readback-layer>
                <div
                    x-ref="backdrop"
                    x-show="sheetMode"
                    x-cloak
                    class="ops-vin-readback-backdrop"
                    @click="closeReadback()"
                ></div>
                <div
                    id="{{ $readbackPanelId }}"
                    x-ref="panel"
                    :class="sheetMode
                        ? 'ops-vin-tooltip ops-vin-readback-sheet'
                        : 'ops-vin-tooltip ops-vin-tooltip--fixed ops-vin-tooltip-layer ops-vin-readback-popover'"
                    :style="sheetMode ? '' : tooltipStyle"
                    role="dialog"
                    aria-modal="true"
                    aria-label="VIN readback"
                    tabindex="-1"
                    data-vin-phonetic-readback
                    @keydown.escape.stop="closeReadback()"
                >
                    <div class="ops-vin-phonetic-head d-flex align-items-start justify-content-between gap-2">
                        <span class="ops-vin-phonetic-label">VIN readback</span>
                        <button type="button" class="ops-vin-readback-close" aria-label="Close VIN readback" @click="closeReadback()">×</button>
                    </div>
                    @include('operations.partials._vin-phonetic-tooltip', [
                        'sections' => $sections,
                        'heading' => false,
                    ])
                </div>
            </div>
        </template>
    </span>
@endif
