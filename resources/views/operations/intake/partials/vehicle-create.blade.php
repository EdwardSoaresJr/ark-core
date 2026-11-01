@php
    $needsVehicle = $customer->vehicles->isEmpty();
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
    <section @class(['card', 'border-primary' => $needsVehicle]) data-intake-vehicle-add>
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $needsVehicle ? 'Add vehicle to continue' : 'Add another vehicle' }}</h3>
                @unless ($needsVehicle)
                    <div class="card-subtitle">Decode a VIN or plate, or enter year, make, and model.</div>
                @endunless
            </div>
        </div>
        <div class="card-body p-2 p-md-3">
            @include('operations.intake.partials.vehicle-create-form', [
                'customer' => $customer,
                'needsVehicle' => $needsVehicle,
                'prefillYear' => old('year', $lead?->vehicle_year),
                'prefillMake' => old('make', $lead?->vehicle_make),
                'prefillModel' => old('model', $lead?->vehicle_model),
            ])
        </div>
    </section>
@else
    @if ($needsVehicle)
        <div class="alert alert-warning mb-0">No vehicle on file. Ask someone with vehicle access to add one before opening an RO.</div>
    @endif
@endcan
