@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $canChangeVehicle = $repairOrder->canChangeVehicle()
        && auth()->user()?->can(ArkCapability::RepairOrdersManage->value);
    $alternateVehicles = $canChangeVehicle
        ? $repairOrder->customer->vehicles()
            ->whereKeyNot($repairOrder->vehicle_id)
            ->orderByDesc('id')
            ->get()
        : collect();
@endphp

@if ($canChangeVehicle)
    <div
        class="mt-1"
        data-vehicle-change
        x-data="arkRepairOrderVehicleChange({
            url: @js(route('operations.repair-orders.vehicle.update', $repairOrder)),
            csrf: @js(csrf_token()),
            repairOrderId: @js($repairOrder->id),
            estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
        })"
    >
        <button
            type="button"
            class="btn btn-link btn-sm p-0"
            @click="open = ! open"
            :aria-expanded="open"
            aria-controls="ro-vehicle-change-panel"
            x-text="open ? 'Cancel vehicle change' : 'Wrong vehicle?'"
        >Wrong vehicle?</button>

        <div id="ro-vehicle-change-panel" x-show="open" x-cloak class="card card-sm mt-2">
            <div class="card-body">
                <p class="small text-secondary mb-2">
                    Choose the vehicle for this RO before adding scopes or line items.
                </p>

                @if ($alternateVehicles->isNotEmpty())
                    <div class="list-group">
                        @foreach ($alternateVehicles as $alternateVehicle)
                            <button
                                type="button"
                                class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2 small fw-medium"
                                :disabled="saving"
                                @click="changeVehicle({{ $alternateVehicle->id }})"
                            >
                                <span>{{ $alternateVehicle->display_name }}</span>
                                <span class="badge bg-azure-lt flex-shrink-0">Use</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="small text-secondary mb-0">No other vehicles on file for this customer.</p>
                @endif

                @can(ArkCapability::VehiclesManage->value)
                    <a
                        href="{{ route('operations.customers.show', $repairOrder->customer) }}#vehicles"
                        class="d-inline-block small mt-2"
                    >
                        Add another vehicle
                    </a>
                @endcan

                <p x-show="error" x-cloak x-text="error" class="small text-danger mt-2 mb-0" role="alert"></p>
                <p x-show="saving" x-cloak class="small text-secondary mt-2 mb-0">Updating vehicle…</p>
            </div>
        </div>
    </div>
@endif
