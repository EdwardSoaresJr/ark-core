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
@endphp

@if ($pressure->showsChip())
    <span
        class="ops-vehicle-identity-pressure-chip ops-vehicle-identity-pressure-chip--{{ $pressure->chipTone() }}"
        @if ($hint)
            title="{{ $hint }}"
        @endif
    >{{ $pressure->label() }}</span>
@endif
