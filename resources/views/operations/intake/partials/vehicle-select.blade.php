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
    class="ops-intake-vehicle-step"
    x-data="arkIntakeVehicleSelect({ total: {{ $vehicleCount }}, blobs: @js($vehicleSearchBlobs) })"
>
    <div class="ops-intake-vehicle-step-head">
        <div class="min-w-0">
            <h2 class="ops-intake-vehicle-step-title">Choose vehicle</h2>
            <p class="ops-intake-vehicle-step-lead">
                @if ($vehicleCount === 0)
                    Add a vehicle to continue intake.
                @elseif ($vehicleCount === 1)
                    Confirm this is the vehicle in the shop today — or add another.
                @else
                    {{ $vehicleCount }} vehicles on file — pick the one in the shop today.
                @endif
            </p>
        </div>
    </div>

    @if (($lastVisitVehicle ?? null) && $vehicleCount > 1)
        <a
            href="{{ route('operations.intake.create', array_merge($intakeWorkspaceParams, ['customer_id' => $customer->id, 'vehicle_id' => $lastVisitVehicle->id])) }}"
            class="ops-intake-last-vehicle"
        >
            <span class="ops-intake-last-vehicle-label">Continue with last vehicle</span>
            <span class="ops-intake-last-vehicle-name">{{ $lastVisitVehicle->display_name }}</span>
            @if ($lastVisitVehicle->plate)
                <span class="ops-intake-last-vehicle-meta">Plate {{ $lastVisitVehicle->plate }}</span>
            @endif
        </a>
    @endif

    @if ($showVehicleSearch)
        <div class="ops-intake-vehicle-search">
            <label for="intake-vehicle-filter" class="ops-index-field-label">Find vehicle</label>
            <input
                id="intake-vehicle-filter"
                type="search"
                x-model="query"
                autocomplete="off"
                autocapitalize="characters"
                enterkeyhint="search"
                placeholder="Year, make, model, plate, or VIN"
                class="ops-intake-control ops-intake-vehicle-search-input"
            >
            <p class="ops-intake-vehicle-search-hint" x-show="! filtering" x-cloak>
                Filter {{ $vehicleCount }} vehicles on this account. Last 4–6 of the VIN or full plate works well.
            </p>
            <p class="ops-intake-vehicle-search-hint ops-intake-vehicle-search-hint--active" x-show="filtering" x-cloak>
                Showing <span x-text="visibleCount"></span> of {{ $vehicleCount }} vehicles
            </p>
        </div>
    @endif

    @if ($vehicleCount > 0)
        <div
            class="ops-intake-vehicle-grid"
            :class="{ 'ops-intake-vehicle-grid--compact': compact }"
        >
            @foreach ($customer->vehicles as $vehicle)
                @php
                    $activeRepairOrder = $vehicle->repairOrders->first();
                    $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
                    $intakeVehicleUrl = route('operations.intake.create', array_merge($intakeWorkspaceParams, [
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                    ]));
                    $vehicleSearchBlob = $vehicleSearchBlobs[$loop->index];
                @endphp
                <div
                    class="ops-intake-vehicle-card-wrap ops-ro-card ops-ro-card--{{ $cardTone }}"
                    x-show="matches(@js($vehicleSearchBlob))"
                >
                    <a href="{{ $intakeVehicleUrl }}" class="ops-intake-vehicle-card-select">
                        <div class="ops-intake-vehicle-card-body">
                            <p class="ops-intake-vehicle-card-ymm">{{ $vehicle->display_name }}</p>
                            @if (! $vehicle->hasVin())
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['vehicle' => $vehicle])
                                </div>
                            @endif
                            @if ($vehicle->nickname && $vehicle->nickname !== $vehicle->display_name)
                                <p class="ops-intake-vehicle-card-nickname">{{ $vehicle->nickname }}</p>
                            @endif
                            <p class="ops-intake-vehicle-card-meta">
                                @if ($vehicle->plate)
                                    <span>Plate {{ $vehicle->plate }}@if ($vehicle->plate_state) / {{ $vehicle->plate_state }} @endif</span>
                                @endif
                                @if ($vehicle->vin)
                                    @if ($vehicle->plate)
                                        <span class="ops-intake-context-sep">·</span>
                                    @endif
                                    <span class="ops-intake-vehicle-card-vin">VIN {{ $vehicle->vin }}</span>
                                @endif
                                @if (! $vehicle->plate && ! $vehicle->vin)
                                    <span>No plate or VIN on file</span>
                                @endif
                            </p>
                        </div>
                        <span class="ops-intake-vehicle-card-action">Use this vehicle</span>
                    </a>
                    @if ($activeRepairOrder)
                        <p class="ops-intake-vehicle-card-footnote ops-ro-footnote">
                            <span class="ops-intake-vehicle-card-ro-label">Active</span>
                            <a
                                href="{{ route('operations.repair-orders.show', $activeRepairOrder) }}"
                                class="ops-ro-footnote-link"
                                aria-label="Open RO #{{ $activeRepairOrder->repair_order_id }}"
                            >
                                <span class="tabular-nums">RO #{{ $activeRepairOrder->repair_order_id }}</span>
                            </a>
                            <span class="ops-ro-sep">·</span>
                            {{ $activeRepairOrder->statusDisplayLabel() }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($showVehicleSearch)
            <p class="ops-intake-vehicle-search-empty" x-show="filtering && visibleCount === 0" x-cloak>
                No vehicles match that filter. Try plate, VIN, or year/make/model.
            </p>
        @endif
    @endif

    @include('operations.intake.partials.vehicle-create', [
        'customer' => $customer,
        'lead' => $lead ?? null,
    ])
</section>
