@php
    $needsVehicle = $customer->vehicles->isEmpty();
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
    <section @class([
        'ops-intake-vehicle-add',
        'ops-intake-vehicle-add--prominent' => $needsVehicle,
        'ops-intake-vehicle-add--inline' => ! $needsVehicle,
    ])>
        <div class="ops-intake-vehicle-add-head">
            <h3 class="ops-intake-vehicle-add-title">{{ $needsVehicle ? 'Add vehicle to continue' : 'Add another vehicle' }}</h3>
            @unless ($needsVehicle)
                <p class="ops-intake-vehicle-add-lead">Decode a VIN or plate, or enter year, make, and model.</p>
            @endunless
        </div>
        @include('operations.intake.partials.vehicle-create-form', [
            'customer' => $customer,
            'needsVehicle' => $needsVehicle,
            'prefillYear' => old('year', $lead?->vehicle_year),
            'prefillMake' => old('make', $lead?->vehicle_make),
            'prefillModel' => old('model', $lead?->vehicle_model),
        ])
    </section>
@else
    @if ($needsVehicle)
        <div class="ops-intake-vehicle-add-empty">
            <p>No vehicle on file. Ask someone with vehicle access to add one before opening an RO.</p>
        </div>
    @endif
@endcan
