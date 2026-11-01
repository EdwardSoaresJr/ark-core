@php
    $intakeWorkspaceParams = $intakeWorkspaceParams ?? [];
    $vehicleCount = $customer->vehicles->count();
    $showVehicleSearch = $vehicleCount >= 4;
    $vehicleSearchBlobs = $customer->vehicles->map(function ($vehicle): string {
        return mb_strtolower(collect([
            $vehicle->display_name,
            $vehicle->nickname,
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
            $vehicle->plate,
            $vehicle->plate_state,
            $vehicle->vin,
        ])->filter(fn ($value) => filled($value))->join(' '));
    })->values()->all();
@endphp

<section
    class="d-flex flex-column gap-3"
    data-intake-vehicle-step
    x-data="arkIntakeVehicleSelect({ total: {{ $vehicleCount }}, blobs: @js($vehicleSearchBlobs) })"
>
    <div class="card">
        <div class="card-body p-2 p-md-3">
            <h2 class="card-title mb-1">Choose vehicle</h2>
            <div class="text-secondary">
                @if ($vehicleCount === 0)
                    Add a vehicle to continue intake.
                @elseif ($vehicleCount === 1)
                    Confirm this is the vehicle in the shop today - or add another.
                @else
                    {{ $vehicleCount }} vehicles on file - pick the one in the shop today.
                @endif
            </div>

            @if (($lastVisitVehicle ?? null) && $vehicleCount > 1)
                <a
                    href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['customer_id' => $customer->id, 'vehicle_id' => $lastVisitVehicle->id])) }}"
                    class="btn btn-outline-primary w-100 justify-content-start text-start mt-3"
                    data-intake-last-vehicle
                >
                    <span class="d-block">
                        <span class="d-block small">Continue with last vehicle</span>
                        <span class="d-block fw-bold">{{ $lastVisitVehicle->display_name }}</span>
                        @if ($lastVisitVehicle->plate)
                            <span class="d-block small">Plate {{ $lastVisitVehicle->plate }}</span>
                        @endif
                    </span>
                </a>
            @endif

            @if ($showVehicleSearch)
                <div class="mt-3">
                    <label for="intake-vehicle-filter" class="form-label">Find vehicle</label>
                    <input
                        id="intake-vehicle-filter"
                        type="search"
                        x-model="query"
                        autocomplete="off"
                        autocapitalize="characters"
                        enterkeyhint="search"
                        placeholder="Year, make, model, plate, or VIN"
                        class="form-control"
                    >
                    <div class="form-hint" x-show="! filtering" x-cloak>
                        Filter {{ $vehicleCount }} vehicles on this account. Last 4–6 of the VIN or full plate works well.
                    </div>
                    <div class="form-hint fw-medium" x-show="filtering" x-cloak>
                        Showing <span x-text="visibleCount"></span> of {{ $vehicleCount }} vehicles
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($vehicleCount > 0)
        <div class="ark-card-grid" data-intake-vehicles>
            @foreach ($customer->vehicles as $vehicle)
                @php
                    $activeRepairOrder = $vehicle->repairOrders->first();
                    $toneColor = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                        $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move',
                    );
                    $intakeVehicleUrl = route('operations.intake.create', array_merge($intakeWorkspaceParams, [
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                    ]));
                    $vehicleSearchBlob = $vehicleSearchBlobs[$loop->index];
                @endphp
                <div x-show="matches(@js($vehicleSearchBlob))">
                    <div class="card card-sm h-100">
                        <div class="card-status-start bg-{{ $toneColor }}"></div>
                        <div class="card-body p-2 p-md-3">
                            <div class="fw-bold">{{ $vehicle->display_name }}</div>
                            @if (! $vehicle->hasVin())
                                <x-tabler.vehicle-identity-chip :vehicle="$vehicle" />
                            @endif
                            @if ($vehicle->nickname && $vehicle->nickname !== $vehicle->display_name)
                                <div class="small text-secondary">{{ $vehicle->nickname }}</div>
                            @endif
                            <div class="small text-secondary text-break">
                                @if ($vehicle->plate)
                                    Plate {{ $vehicle->plate }}@if ($vehicle->plate_state) / {{ $vehicle->plate_state }} @endif
                                @endif
                                @if ($vehicle->plate && $vehicle->vin) · @endif
                                @if ($vehicle->vin)
                                    VIN {{ $vehicle->vin }}
                                @endif
                                @if (! $vehicle->plate && ! $vehicle->vin)
                                    No plate or VIN on file
                                @endif
                            </div>
                            <a href="{{ $intakeVehicleUrl }}" class="btn btn-primary w-100 mt-2">Use this vehicle</a>
                        </div>
                        @if ($activeRepairOrder)
                            <div class="card-footer p-2 small text-secondary" data-intake-vehicle-active-ro>
                                Active
                                <a href="{{ route('operations.repair-orders.show', $activeRepairOrder) }}" aria-label="Open RO #{{ $activeRepairOrder->repair_order_id }}" class="ark-money">RO #{{ $activeRepairOrder->repair_order_id }}</a>
                                · {{ $activeRepairOrder->statusDisplayLabel() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($showVehicleSearch)
            <div class="text-secondary" x-show="filtering && visibleCount === 0" x-cloak>
                No vehicles match that filter. Try plate, VIN, or year/make/model.
            </div>
        @endif
    @endif

    @include('operations.intake.partials.vehicle-create', [
        'customer' => $customer,
        'lead' => $lead ?? null,
    ])
</section>
