<div class="border-b border-slate-100 px-3 py-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="ops-eyebrow">Fleet</p>
        @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
            <button
                type="button"
                title="Open to add vehicle"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle-create', invokeEl: $event.currentTarget } }))"
                class="min-h-8 rounded-sm border border-slate-300 px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
            >
                Add vehicle
            </button>
        @endcan
    </div>
</div>
<div x-data="{ activeRoVehicleId: null, pendingRemoveVehicleId: null }" class="divide-y divide-slate-100">
    @forelse ($customer->vehicles as $vehicle)
        @php
            $identityDetails = collect([$vehicle->engine, $vehicle->drive, $vehicle->transmission])->filter()->join(' / ');
            $vehicleActiveRepairOrder = $vehicle->repairOrders->first(fn ($repairOrder) => in_array($repairOrder->status->value, $activeStatuses, true));
            $vehicleLastRepairOrder = $vehicle->repairOrders->first();
            $vehicleFutureWorkOrders = $vehicle->repairOrders->filter(fn ($repairOrder) => $repairOrder->futureWorkCount() > 0);
            $vehicleFutureWorkCount = $vehicleFutureWorkOrders->sum(fn ($repairOrder) => $repairOrder->futureWorkCount());
            $vehicleFutureWorkCents = $vehicleFutureWorkOrders->sum(fn ($repairOrder) => $repairOrder->futureWorkSubtotalCents());
            $vehicleFutureWorkConcerns = $vehicleFutureWorkOrders->flatMap(fn ($repairOrder) => $repairOrder->futureWorkConcerns());
            $vehicleStrongestFutureWorkIntent = \App\Ark\Operations\RepairOrders\RecommendationIntent::strongestDeferredFollowUp($vehicleFutureWorkConcerns);
            $vehicleUrgentFutureWork = $vehicleStrongestFutureWorkIntent === \App\Ark\Operations\RepairOrders\RecommendationIntent::ImmediateAttention;
            $vehicleFutureWorkReminder = $vehicleStrongestFutureWorkIntent?->continuityReminder() ?? 'Deferred work retained for next-visit continuity.';
            $vehiclePosture = $vehicleActiveRepairOrder
                ? $vehicleActiveRepairOrder->status->label()
                : ($vehicleLastRepairOrder ? 'Last seen '.$vehicleLastRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j') : 'No service history');
        @endphp

        <section
            id="vehicle-{{ $vehicle->id }}"
            class="border-l-4 px-3 py-2.5 hover:bg-slate-50/40 {{ $vehicleActiveRepairOrder ? 'border-l-amber-500' : 'border-l-slate-300' }}"
        >
            <div>
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                            <a
                                href="{{ route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]) }}"
                                class="text-base font-black leading-5 text-slate-950 hover:text-slate-700"
                            >{{ $vehicle->operational_identity }}</a>
                            <span class="ops-state-pill {{ $vehicleActiveRepairOrder ? 'border-amber-300 bg-amber-50 text-amber-800' : '' }}">{{ $vehiclePosture }}</span>
                        </div>
                        @if ($identityDetails)
                            <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $identityDetails }}</p>
                        @else
                            <p class="mt-0.5 text-xs text-slate-500">Powertrain not recorded</p>
                        @endif

                        <p class="ops-meta mt-1">
                            Plate {{ $vehicle->plate ?: 'n/a' }}@if ($vehicle->plate_state) / {{ $vehicle->plate_state }} @endif
                            <span class="mx-1 text-slate-300">•</span>
                            @if ($vehicle->normalized_vin || $vehicle->vin)
                                <x-operations.vin-display :vin="$vehicle->normalized_vin ?: $vehicle->vin" />
                            @else
                                VIN n/a
                            @endif
                            @if ($vehicle->color)
                                <span class="mx-1 text-slate-300">•</span>
                                {{ $vehicle->color }}
                            @endif
                        </p>

                        @if ($vehicleActiveRepairOrder)
                            <a href="{{ route('operations.repair-orders.show', $vehicleActiveRepairOrder) }}" class="mt-2 grid gap-1 border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs hover:border-amber-300 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                                <span class="font-black uppercase tracking-[0.08em] text-amber-800">Active RO #{{ $vehicleActiveRepairOrder->repair_order_id }}</span>
                                <span class="truncate font-semibold text-slate-800">{{ $vehicleActiveRepairOrder->concern_summary }}</span>
                                <span class="font-bold text-slate-600">{{ $vehicleActiveRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j, g:i A') }}</span>
                            </a>
                        @elseif ($vehicleLastRepairOrder)
                            <a href="{{ route('operations.repair-orders.show', $vehicleLastRepairOrder) }}" class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 hover:text-slate-800">
                                <span class="font-bold uppercase tracking-[0.08em] text-slate-400">Last Visit</span>
                                <span class="font-semibold text-slate-700">RO #{{ $vehicleLastRepairOrder->repair_order_id }}</span>
                                <span>{{ $vehicleLastRepairOrder->status->label() }}</span>
                                <span>{{ $vehicleLastRepairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j') }}</span>
                            </a>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2" aria-label="Vehicle commands">
                        @if (\App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
                            <a
                                href="{{ \App\Ark\Operations\Appointments\ScheduleUrl::to([
                                    'customer' => $customer->id,
                                    'vehicle' => $vehicle->id,
                                ]) }}"
                                class="inline-flex min-h-10 items-center justify-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 sm:min-h-8"
                            >
                                Schedule
                            </a>
                        @endif
                        @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                            <button
                                type="button"
                                @click="activeRoVehicleId = activeRoVehicleId === {{ $vehicle->id }} ? null : {{ $vehicle->id }}"
                                :aria-expanded="(activeRoVehicleId === {{ $vehicle->id }}).toString()"
                                aria-controls="vehicle-ro-create-{{ $vehicle->id }}"
                                class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800 sm:min-h-8"
                            >
                                Start RO
                            </button>
                        @endcan

                        @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                            <button
                                type="button"
                                title="Open to edit vehicle"
                                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle', context: { vehicleId: {{ $vehicle->id }} }, invokeEl: $event.currentTarget } }))"
                                class="min-h-10 rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 sm:min-h-8"
                            >
                                Edit Vehicle
                            </button>
                            @if ($vehicle->repair_orders_count === 0)
                                <div class="inline-flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        x-show="pendingRemoveVehicleId !== {{ $vehicle->id }}"
                                        @click="pendingRemoveVehicleId = {{ $vehicle->id }}"
                                        class="min-h-10 rounded-sm border border-rose-200 px-3 text-xs font-semibold text-rose-700 hover:border-rose-300 hover:bg-rose-50 sm:min-h-8"
                                    >
                                        Remove Vehicle
                                    </button>
                                    <form
                                        x-show="pendingRemoveVehicleId === {{ $vehicle->id }}"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('operations.customers.vehicles.destroy', [$customer, $vehicle]) }}"
                                        class="inline-flex flex-wrap items-center gap-2"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <span class="text-xs font-semibold text-rose-800">Remove {{ $vehicle->operational_identity }}?</span>
                                        <button type="submit" class="min-h-10 rounded-sm bg-rose-700 px-3 text-xs font-semibold text-white hover:bg-rose-800 sm:min-h-8">
                                            Confirm Remove
                                        </button>
                                        <button type="button" @click="pendingRemoveVehicleId = null" class="min-h-10 rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 sm:min-h-8">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>

                @if ($vehicle->public_notes || $vehicle->private_notes)
                    <div class="mt-2 space-y-1 border-l-2 border-slate-200 bg-slate-50/60 px-3 py-2 text-sm leading-5 text-slate-700">
                        @if ($vehicle->public_notes)
                            <p>{{ $vehicle->public_notes }}</p>
                        @endif
                        @if ($vehicle->private_notes)
                            <p class="text-slate-500">{{ $vehicle->private_notes }}</p>
                        @endif
                    </div>
                @endif

                @if ($vehicleFutureWorkCount > 0)
                    <div class="mt-2 border-l-4 {{ $vehicleUrgentFutureWork ? 'border-amber-400 bg-amber-50' : 'border-slate-300 bg-slate-50' }} px-3 py-2 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-black text-slate-950">{{ $vehicleFutureWorkCount }} future-work {{ Str::plural('item', $vehicleFutureWorkCount) }}</p>
                            <p class="font-black tabular-nums text-slate-800">${{ number_format($vehicleFutureWorkCents / 100, 2) }}</p>
                        </div>
                        <p class="mt-0.5 text-xs font-semibold leading-4 {{ $vehicleUrgentFutureWork ? 'text-amber-900' : 'text-slate-600' }}">
                            {{ $vehicleFutureWorkReminder }}
                        </p>
                        <div class="mt-1.5 grid gap-1">
                            @foreach ($vehicleFutureWorkOrders->take(2) as $futureWorkOrder)
                                <a href="{{ route('operations.repair-orders.show', $futureWorkOrder) }}" class="flex items-center justify-between gap-2 text-xs font-semibold text-slate-600 hover:text-slate-950">
                                    <span class="truncate">RO #{{ $futureWorkOrder->repair_order_id }} · {{ $futureWorkOrder->futureWorkSummary() }}</span>
                                    <span class="shrink-0 tabular-nums">${{ number_format($futureWorkOrder->futureWorkSubtotalCents() / 100, 2) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($vehicle->repairOrders->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                        @foreach ($vehicle->repairOrders->take(3) as $vehicleRepairOrder)
                            <a href="{{ route('operations.repair-orders.show', $vehicleRepairOrder) }}" class="inline-flex min-h-8 items-center gap-1 border border-slate-200 bg-white px-2 py-1 font-semibold text-slate-600 hover:border-slate-300 hover:text-slate-950">
                                <span>RO #{{ $vehicleRepairOrder->repair_order_id }}</span>
                                <span class="text-slate-300">·</span>
                                <span>{{ $vehicleRepairOrder->status->label() }}</span>
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
                        class="mt-3 border border-slate-200 bg-white p-3 shadow-sm"
                    >
                        @csrf
                        <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                        <label class="block text-xs font-medium text-slate-500">
                            Reason for visit
                            <textarea name="visit_reason" required rows="2" placeholder="Why {{ $vehicle->display_name }} is here" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400"></textarea>
                        </label>
                        <div class="mt-2 flex justify-end gap-2">
                            <button type="button" @click="activeRoVehicleId = null" class="min-h-10 rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 sm:min-h-8">
                                Cancel
                            </button>
                            <button type="submit" class="min-h-10 rounded-sm bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800 sm:min-h-8">
                                Create Estimate / RO
                            </button>
                        </div>
                    </form>
                @endcan

            </div>
        </section>
    @empty
        <div class="px-5 py-8 text-sm text-slate-600">
            No vehicles yet.
            @can(App\Ark\Runtime\Authorization\ArkCapability::VehiclesManage->value)
                <button
                    type="button"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-vehicle-create', invokeEl: $event.currentTarget } }))"
                    class="ml-1 font-semibold text-slate-950 hover:text-slate-700"
                >Add one</button>
                to open an RO draft.
            @endcan
        </div>
    @endforelse
</div>
