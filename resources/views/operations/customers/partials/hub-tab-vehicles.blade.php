<div class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2">
    <h3 class="card-title mb-0 me-auto">Fleet</h3>
    @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
        <button
            type="button"
            class="btn"
            title="Open to add vehicle"
            x-on:click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle-create', invokeEl: $event.currentTarget } }))"
        >
            <x-tabler.icon name="plus" />
            Add vehicle
        </button>
    @endcan
</div>

<div class="card-body" x-data="{ activeRoVehicleId: null, pendingRemoveVehicleId: null }">
    @if ($customer->vehicles->isEmpty())
        <div class="text-secondary">
            No vehicles yet.
            @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                <button
                    type="button"
                    class="btn btn-link px-1"
                    x-on:click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle-create', invokeEl: $event.currentTarget } }))"
                >Add one</button>
                to open an RO draft.
            @endcan
        </div>
    @else
        <div class="ark-card-grid" style="--ark-card-min-width: 22rem">
            @foreach ($customer->vehicles as $vehicle)
                @php
                    $identityDetails = collect([$vehicle->engine, $vehicle->drive, $vehicle->transmission])->filter()->join(' / ');
                    $vehicleActiveRepairOrder = $vehicle->repairOrders->first(fn ($repairOrder) => in_array($repairOrder->status->value, $activeStatuses, true));
                    $vehicleLastRepairOrder = $vehicle->repairOrders->first();
                    $vehicleFutureWorkOrders = $vehicle->repairOrders->filter(fn ($repairOrder) => $repairOrder->futureWorkCount() > 0);
                    $vehicleFutureWorkCount = $vehicleFutureWorkOrders->sum(fn ($repairOrder) => $repairOrder->futureWorkCount());
                    $vehicleFutureWorkCents = $vehicleFutureWorkOrders->sum(fn ($repairOrder) => $repairOrder->futureWorkSubtotalCents());
                    $vehicleFutureWorkConcerns = $vehicleFutureWorkOrders->flatMap(fn ($repairOrder) => $repairOrder->futureWorkConcerns());
                    $vehicleStrongestFutureWork = \App\Ark\Operations\RepairOrders\RecommendationPresentation::strongestDeferred($vehicleFutureWorkConcerns);
                    $vehicleUrgentFutureWork = $vehicleStrongestFutureWork?->priority === \App\Ark\Operations\RepairOrders\RecommendationPriority::MustDo;
                    $vehicleFutureWorkReminder = $vehicleStrongestFutureWork?->continuityReminder() ?? 'Deferred work retained for next-visit continuity.';
                    $vehiclePosture = $vehicleActiveRepairOrder
                        ? $vehicleActiveRepairOrder->status->label()
                        : ($vehicleLastRepairOrder ? 'Last seen '.$vehicleLastRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j') : 'No service history');
                    $vehicleRecommendationAwareness = \App\Ark\Operations\Recommendations\RecommendationAwarenessProjection::forVehicle($vehicle);
                @endphp

                <section id="vehicle-{{ $vehicle->id }}" class="card card-sm">
                    <div class="card-status-start {{ $vehicleActiveRepairOrder ? 'bg-orange' : 'bg-secondary' }}"></div>
                    <div class="card-body">
                        <a
                            href="{{ route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]) }}"
                            class="d-block fw-bold text-reset text-truncate"
                            title="{{ $vehicle->display_name }}"
                        >{{ $vehicle->operational_identity }}</a>
                        <span class="badge mt-1 {{ $vehicleActiveRepairOrder ? 'bg-orange-lt' : 'bg-secondary-lt' }}">{{ $vehiclePosture }}</span>

                        @if ($identityDetails)
                            <div class="small fw-medium mt-2">{{ $identityDetails }}</div>
                        @else
                            <div class="small text-secondary mt-2">Powertrain not recorded</div>
                        @endif

                        @if ($vehicleRecommendationAwareness['headline'])
                            <div @class(['small fw-medium mt-1', 'text-danger' => $vehicleRecommendationAwareness['strong']])>
                                {{ $vehicleRecommendationAwareness['headline'] }}
                            </div>
                        @endif

                        <div class="small text-secondary mt-1">
                            Plate {{ $vehicle->plate ?: 'n/a' }}@if ($vehicle->plate_state) / {{ $vehicle->plate_state }}@endif
                            ·
                            @if ($vehicle->normalized_vin || $vehicle->vin)
                                <span class="ark-legacy"><x-operations.vin-display :vin="$vehicle->normalized_vin ?: $vehicle->vin" /></span>
                            @else
                                VIN n/a
                            @endif
                            @if ($vehicle->color)
                                · {{ $vehicle->color }}
                            @endif
                        </div>

                        @if ($vehicleActiveRepairOrder)
                            <a
                                href="{{ route('operations.repair-orders.show', $vehicleActiveRepairOrder) }}"
                                class="d-block mt-2 p-2 rounded border border-orange bg-orange-lt text-reset small"
                            >
                                <span class="fw-bold text-orange">Active RO #{{ $vehicleActiveRepairOrder->repair_order_id }}</span>
                                <span class="d-block text-truncate fw-medium">{{ $vehicleActiveRepairOrder->concern_summary }}</span>
                                <span class="d-block text-secondary">{{ $vehicleActiveRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j, g:i A') }}</span>
                            </a>
                        @elseif ($vehicleLastRepairOrder)
                            <a
                                href="{{ route('operations.repair-orders.show', $vehicleLastRepairOrder) }}"
                                class="d-block mt-2 small text-secondary"
                            >
                                <span class="subheader">Last visit</span>
                                RO #{{ $vehicleLastRepairOrder->repair_order_id }}
                                · {{ $vehicleLastRepairOrder->status->label() }}
                                · {{ $vehicleLastRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j') }}
                            </a>
                        @endif

                        @if ($vehicle->public_notes || $vehicle->private_notes)
                            <div class="mt-2 ps-2 border-start border-2 small">
                                @if ($vehicle->public_notes)
                                    <div>{{ $vehicle->public_notes }}</div>
                                @endif
                                @if ($vehicle->private_notes)
                                    <div class="text-secondary">{{ $vehicle->private_notes }}</div>
                                @endif
                            </div>
                        @endif

                        @if ($vehicleFutureWorkCount > 0)
                            <div @class([
                                'mt-2 p-2 rounded border small',
                                'border-orange bg-orange-lt' => $vehicleUrgentFutureWork,
                            ])>
                                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                                    <span class="fw-bold">{{ $vehicleFutureWorkCount }} future-work {{ Str::plural('item', $vehicleFutureWorkCount) }}</span>
                                    <span class="fw-bold">${{ number_format($vehicleFutureWorkCents / 100, 2) }}</span>
                                </div>
                                <div @class(['fw-medium', 'text-orange' => $vehicleUrgentFutureWork, 'text-secondary' => ! $vehicleUrgentFutureWork])>
                                    {{ $vehicleFutureWorkReminder }}
                                </div>
                                @foreach ($vehicleFutureWorkOrders->take(2) as $futureWorkOrder)
                                    <a href="{{ route('operations.repair-orders.show', $futureWorkOrder) }}" class="d-flex justify-content-between gap-2 mt-1 text-reset">
                                        <span class="text-truncate">RO #{{ $futureWorkOrder->repair_order_id }} · {{ $futureWorkOrder->futureWorkSummary() }}</span>
                                        <span class="flex-shrink-0">${{ number_format($futureWorkOrder->futureWorkSubtotalCents() / 100, 2) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($vehicle->repairOrders->isNotEmpty())
                            <div class="btn-list mt-2">
                                @foreach ($vehicle->repairOrders->take(3) as $vehicleRepairOrder)
                                    <a href="{{ route('operations.repair-orders.show', $vehicleRepairOrder) }}" class="btn btn-sm">
                                        RO #{{ $vehicleRepairOrder->repair_order_id }} · {{ $vehicleRepairOrder->status->label() }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                            <form
                                id="vehicle-ro-create-{{ $vehicle->id }}"
                                x-show="activeRoVehicleId === {{ $vehicle->id }}"
                                x-cloak
                                method="POST"
                                action="{{ route('operations.customers.repair-orders.drafts.store', $customer) }}"
                                class="mt-3 pt-3 border-top"
                            >
                                @csrf
                                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                <label for="vehicle-ro-reason-{{ $vehicle->id }}" class="form-label">Reason for visit</label>
                                <textarea
                                    id="vehicle-ro-reason-{{ $vehicle->id }}"
                                    name="visit_reason"
                                    required
                                    rows="2"
                                    placeholder="Why {{ $vehicle->display_name }} is here"
                                    class="form-control"
                                ></textarea>
                                <div class="btn-list justify-content-end mt-2">
                                    <button type="button" class="btn" x-on:click="activeRoVehicleId = null">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Create Estimate / RO</button>
                                </div>
                            </form>
                        @endcan
                    </div>

                    <div class="card-footer">
                        <div class="btn-list" aria-label="Vehicle commands">
                            @if (\App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
                                <a
                                    href="{{ \App\Ark\Operations\Appointments\ScheduleUrl::to([
                                        'customer' => $customer->id,
                                        'vehicle' => $vehicle->id,
                                    ]) }}"
                                    class="btn"
                                >
                                    <x-tabler.icon name="calendar" />
                                    Schedule
                                </a>
                            @endif
                            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    x-on:click="activeRoVehicleId = activeRoVehicleId === {{ $vehicle->id }} ? null : {{ $vehicle->id }}"
                                    :aria-expanded="(activeRoVehicleId === {{ $vehicle->id }}).toString()"
                                    aria-controls="vehicle-ro-create-{{ $vehicle->id }}"
                                >
                                    Start RO
                                </button>
                            @endcan

                            @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                                <button
                                    type="button"
                                    class="btn"
                                    title="Open to edit vehicle"
                                    x-on:click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle', context: { vehicleId: {{ $vehicle->id }} }, invokeEl: $event.currentTarget } }))"
                                >
                                    <x-tabler.icon name="pencil" />
                                    Edit Vehicle
                                </button>
                                @if ($vehicle->repair_orders_count === 0)
                                    <button
                                        type="button"
                                        class="btn btn-ghost-danger"
                                        x-show="pendingRemoveVehicleId !== {{ $vehicle->id }}"
                                        x-on:click="pendingRemoveVehicleId = {{ $vehicle->id }}"
                                    >
                                        <x-tabler.icon name="trash" />
                                        Remove Vehicle
                                    </button>
                                @endif
                            @endcan
                        </div>

                        @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                            @if ($vehicle->repair_orders_count === 0)
                                <form
                                    x-show="pendingRemoveVehicleId === {{ $vehicle->id }}"
                                    x-cloak
                                    method="POST"
                                    action="{{ route('operations.customers.vehicles.destroy', [$customer, $vehicle]) }}"
                                    class="mt-2 p-2 rounded border border-danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <div class="small fw-medium text-danger mb-2">Remove {{ $vehicle->operational_identity }}?</div>
                                    <div class="btn-list">
                                        <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                        <button type="button" class="btn" x-on:click="pendingRemoveVehicleId = null">Cancel</button>
                                    </div>
                                </form>
                            @endif
                        @endcan
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
