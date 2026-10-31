@props(['vehicle'])

@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle $vehicle */
    $pressure = $vehicle->identityPressure();
    $hint = $vehicle->identityPressureHint();
@endphp

@if ($pressure->showsChip())
    <span
        {{ $attributes->class(['badge bg-yellow-lt']) }}
        @if ($hint)
            title="{{ $hint }}"
            data-bs-toggle="tooltip"
        @endif
    >{{ $pressure->label() }}</span>
@endif
