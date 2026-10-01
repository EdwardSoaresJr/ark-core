@php
    $pressure = $partsPressure ?? $repairOrder->partsPressure();
    $summary = $partsPressureSummary ?? $repairOrder->partsPressureSummary($pressure);
    $label = $partsPressureLabel ?? $repairOrder->partsPressureLabel();
@endphp

@if ($pressure->showsChip())
    <span
        class="ops-parts-pressure-chip ops-parts-pressure-chip--{{ $pressure->chipTone() }}"
        @if ($summary)
            title="{{ $summary }}"
        @endif
    >{{ $label }}</span>
@endif
