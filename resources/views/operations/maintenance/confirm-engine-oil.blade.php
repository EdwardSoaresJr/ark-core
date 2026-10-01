@php
    use App\Ark\Operations\Maintenance\EngineOilPreparedIncludes;
    use App\Ark\Operations\Maintenance\MaintenanceWasherState;

    /** @var \App\Ark\Operations\Maintenance\MaintenanceService $service */
    $preparedIncludes = EngineOilPreparedIncludes::bullets($service);
@endphp

<x-operations.app :title="'Confirm Oil Installed · '.$repairOrder->repair_order_id">
    <div class="mx-auto max-w-lg px-4 py-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Engine Oil Service</p>
        <h1 class="mt-1 text-xl font-semibold text-slate-900">
            {{ $isCorrection ? 'Correct Installed Oil' : 'Confirm Installed' }}
        </h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ $repairOrder->vehicle?->year }} {{ $repairOrder->vehicle?->make }} {{ $repairOrder->vehicle?->model }}
            · RO {{ $repairOrder->repair_order_id }}
        </p>

        @if ($preparedIncludes !== [])
            <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Prepared</p>
                <ul class="mt-1 list-disc pl-4">
                    @foreach ($preparedIncludes as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('operations.repair-orders.maintenance.confirm.store', [$repairOrder, $service]) }}"
            class="mt-6 space-y-4"
        >
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-600" for="oil_brand">Brand / Product</label>
                <input
                    id="oil_brand"
                    name="oil_brand"
                    required
                    value="{{ old('oil_brand', $service->prepared_oil_brand ?? $priorEvent?->oil_brand) }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    list="oil-brand-suggestions"
                    autocomplete="off"
                >
                <datalist id="oil-brand-suggestions">
                    <option value="Mobil 1 Full Synthetic"></option>
                    <option value="Mobil 1 ESP"></option>
                    <option value="Mobil 1 Extended Performance"></option>
                    <option value="Pennzoil Platinum"></option>
                    <option value="Castrol EDGE"></option>
                </datalist>
                @error('oil_brand') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="viscosity">Viscosity</label>
                    <input
                        id="viscosity"
                        name="viscosity"
                        required
                        value="{{ old('viscosity', $service->prepared_viscosity ?? $priorEvent?->viscosity) }}"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        list="viscosity-suggestions"
                        placeholder="5W-30"
                        autocomplete="off"
                    >
                    <datalist id="viscosity-suggestions">
                        <option value="0W-20"></option>
                        <option value="5W-20"></option>
                        <option value="5W-30"></option>
                        <option value="0W-40"></option>
                    </datalist>
                    @error('viscosity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="quantity_qt">Quantity (qt)</label>
                    <input
                        id="quantity_qt"
                        name="quantity_qt"
                        type="number"
                        step="0.1"
                        min="0.1"
                        required
                        value="{{ old('quantity_qt', $service->prepared_quantity_qt ?? $priorEvent?->quantity_qt) }}"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                    @error('quantity_qt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600" for="filter_part">Filter</label>
                <input
                    id="filter_part"
                    name="filter_part"
                    required
                    value="{{ old('filter_part', $service->prepared_filter_part ?? $priorEvent?->filter_part) }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    placeholder="WIX 57035"
                    autocomplete="off"
                >
                @error('filter_part') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <fieldset>
                <legend class="text-xs font-medium text-slate-600">Drain plug washer</legend>
                <div class="mt-2 space-y-2 text-sm">
                    @foreach ([
                        MaintenanceWasherState::Installed,
                        MaintenanceWasherState::NotRequired,
                        MaintenanceWasherState::NotReplaced,
                    ] as $washerOption)
                        <label class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="washer"
                                value="{{ $washerOption->value }}"
                                @checked(old('washer', MaintenanceWasherState::Installed->value) === $washerOption->value)
                                required
                            >
                            <span>{{ $washerOption->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('washer') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label class="block text-xs font-medium text-slate-600" for="service_mileage">Mileage</label>
                <input
                    id="service_mileage"
                    name="service_mileage"
                    type="number"
                    min="1"
                    required
                    value="{{ old('service_mileage', $defaultMileage) }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                @error('service_mileage') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="reset_reminder" value="1" @checked(old('reset_reminder', $service->reset_reminder))>
                Reset maintenance reminder
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Confirm Installed
                </button>
                <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="text-sm text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-operations.app>
