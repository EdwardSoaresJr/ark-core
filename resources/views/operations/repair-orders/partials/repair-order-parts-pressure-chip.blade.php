@php
    $pressure = $partsPressure ?? $repairOrder->partsPressure();
    $summary = $partsPressureSummary ?? $repairOrder->partsPressureSummary($pressure);
    $label = $partsPressureLabel ?? $repairOrder->partsPressureLabel();
    $ui = $ui ?? null;
@endphp

@if ($pressure->showsChip())
    @if ($ui === 'tabler')
        <span
            class="badge bg-{{ match ($pressure->chipTone()) {
                'backordered' => 'red',
                'partial' => 'orange',
                'waiting' => 'yellow',
                default => 'secondary',
            } }}-lt"
            data-parts-pressure="{{ $pressure->chipTone() }}"
            @if ($summary)
                title="{{ $summary }}"
            @endif
        >{{ $label }}</span>
    @else
        <span
            class="ops-parts-pressure-chip ops-parts-pressure-chip--{{ $pressure->chipTone() }}"
            @if ($summary)
                title="{{ $summary }}"
            @endif
        >{{ $label }}</span>
    @endif
@endif
