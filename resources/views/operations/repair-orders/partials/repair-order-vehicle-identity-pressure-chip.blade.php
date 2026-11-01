@php
    use App\Ark\Operations\Vehicles\Vehicle;
    use App\Ark\Operations\Vehicles\VehicleIdentityPressure;

    $repairOrder = $repairOrder ?? null;
    $vehicle = $vehicle ?? $repairOrder?->vehicle;
    $pressure = $vehicleIdentityPressure ?? ($repairOrder
        ? $repairOrder->vehicleIdentityPressure()
        : ($vehicle instanceof Vehicle ? $vehicle->identityPressure() : VehicleIdentityPressure::NoVin));
    $hint = $vehicleIdentityPressureHint ?? ($repairOrder
        ? $repairOrder->vehicleIdentityPressureHint()
        : ($vehicle instanceof Vehicle ? $vehicle->identityPressureHint() : null));
    $ui = $ui ?? null;
@endphp

@if ($pressure->showsChip())
    @if ($ui === 'tabler')
        <span
            class="badge bg-yellow-lt"
            data-vehicle-identity-pressure="{{ $pressure->chipTone() }}"
            @if ($hint)
                title="{{ $hint }}"
            @endif
        >{{ $pressure->label() }}</span>
    @else
        <span
            class="ops-vehicle-identity-pressure-chip ops-vehicle-identity-pressure-chip--{{ $pressure->chipTone() }}"
            @if ($hint)
                title="{{ $hint }}"
            @endif
        >{{ $pressure->label() }}</span>
    @endif
@endif
