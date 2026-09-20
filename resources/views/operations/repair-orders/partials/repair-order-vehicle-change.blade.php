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
        class="ops-vehicle-change mt-1"
        x-data="arkRepairOrderVehicleChange({
            url: @js(route('operations.repair-orders.vehicle.update', $repairOrder)),
            csrf: @js(csrf_token()),
            repairOrderId: @js($repairOrder->id),
            estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
        })"
    >
        <button
            type="button"
            class="text-[11px] font-semibold text-sky-800 underline decoration-sky-300 underline-offset-2 hover:text-sky-950"
            @click="open = ! open"
            x-text="open ? 'Cancel vehicle change' : 'Wrong vehicle?'"
        ></button>

        <div x-show="open" x-cloak class="mt-1.5 rounded-sm border border-slate-200 bg-white p-2">
            <p class="text-[11px] font-semibold leading-4 text-slate-600">
                Choose the vehicle for this RO before adding scopes or line items.
            </p>

            @if ($alternateVehicles->isNotEmpty())
                <div class="mt-2 space-y-1">
                    @foreach ($alternateVehicles as $alternateVehicle)
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-sm border border-slate-200 px-2 py-1.5 text-left text-xs font-semibold text-slate-800 hover:border-slate-300 hover:bg-slate-50"
                            :disabled="saving"
                            @click="changeVehicle({{ $alternateVehicle->id }})"
                        >
                            <span>{{ $alternateVehicle->display_name }}</span>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-sky-700">Use</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-xs leading-4 text-slate-500">No other vehicles on file for this customer.</p>
            @endif

            @can(ArkCapability::VehiclesManage->value)
                <a
                    href="{{ route('operations.customers.show', $repairOrder->customer) }}#vehicles"
                    class="mt-2 inline-flex text-[11px] font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-950"
                >
                    Add another vehicle
                </a>
            @endcan

            <p x-show="error" x-cloak x-text="error" class="mt-2 text-[11px] font-semibold text-rose-700"></p>
            <p x-show="saving" x-cloak class="mt-2 text-[11px] font-semibold text-slate-400">Updating vehicle…</p>
        </div>
    </div>
@endif
