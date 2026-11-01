@php
    use App\Ark\Operations\Maintenance\EngineOilPreparedIncludes;
    use App\Ark\Operations\Maintenance\MaintenanceWasherState;

    /** @var \App\Ark\Operations\Maintenance\MaintenanceService $service */
    $preparedIncludes = EngineOilPreparedIncludes::bullets($service);
@endphp

<x-operations.app :title="'Confirm Oil Installed · '.$repairOrder->repair_order_id" ui="tabler">
    <x-tabler.page
        layout="focused"
        pretitle="Engine Oil Service"
        :title="$isCorrection ? 'Correct Installed Oil' : 'Confirm Installed'"
    >
        <div class="d-flex flex-column gap-3" data-engine-oil-confirm>
            <div class="text-secondary">
                {{ $repairOrder->vehicle?->year }} {{ $repairOrder->vehicle?->make }} {{ $repairOrder->vehicle?->model }}
                · RO {{ $repairOrder->repair_order_id }}
            </div>

            @if ($preparedIncludes !== [])
                <div class="alert alert-info mb-0" data-engine-oil-prepared>
                    <h4 class="alert-title">Prepared</h4>
                    <ul class="mb-0 ps-3" style="list-style: disc">
                        @foreach ($preparedIncludes as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('operations.repair-orders.maintenance.confirm.store', [$repairOrder, $service]) }}"
                class="card"
            >
                @csrf

                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label" for="oil_brand">Brand / Product</label>
                        <input
                            id="oil_brand"
                            name="oil_brand"
                            required
                            value="{{ old('oil_brand', $service->prepared_oil_brand ?? $priorEvent?->oil_brand) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('oil_brand')])
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
                        @error('oil_brand') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="viscosity">Viscosity</label>
                            <input
                                id="viscosity"
                                name="viscosity"
                                required
                                value="{{ old('viscosity', $service->prepared_viscosity ?? $priorEvent?->viscosity) }}"
                                @class(['form-control', 'is-invalid' => $errors->has('viscosity')])
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
                            @error('viscosity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="quantity_qt">Quantity (qt)</label>
                            <input
                                id="quantity_qt"
                                name="quantity_qt"
                                type="number"
                                step="0.1"
                                min="0.1"
                                required
                                value="{{ old('quantity_qt', $service->prepared_quantity_qt ?? $priorEvent?->quantity_qt) }}"
                                @class(['form-control', 'is-invalid' => $errors->has('quantity_qt')])
                            >
                            @error('quantity_qt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="filter_part">Filter</label>
                        <input
                            id="filter_part"
                            name="filter_part"
                            required
                            value="{{ old('filter_part', $service->prepared_filter_part ?? $priorEvent?->filter_part) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('filter_part')])
                            placeholder="WIX 57035"
                            autocomplete="off"
                        >
                        @error('filter_part') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label">Drain plug washer</legend>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column">
                            @foreach ([
                                MaintenanceWasherState::Installed,
                                MaintenanceWasherState::NotRequired,
                                MaintenanceWasherState::NotReplaced,
                            ] as $washerOption)
                                <label class="form-selectgroup-item flex-fill">
                                    <input
                                        type="radio"
                                        name="washer"
                                        value="{{ $washerOption->value }}"
                                        class="form-selectgroup-input"
                                        @checked(old('washer', MaintenanceWasherState::Installed->value) === $washerOption->value)
                                        required
                                    >
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <span class="me-3">
                                            <span class="form-selectgroup-check"></span>
                                        </span>
                                        <span>{{ $washerOption->label() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('washer') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </fieldset>

                    <div class="mb-3">
                        <label class="form-label" for="service_mileage">Mileage</label>
                        <input
                            id="service_mileage"
                            name="service_mileage"
                            type="number"
                            min="1"
                            required
                            value="{{ old('service_mileage', $defaultMileage) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('service_mileage')])
                        >
                        @error('service_mileage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <label class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" name="reset_reminder" value="1" @checked(old('reset_reminder', $service->reset_reminder))>
                        <span class="form-check-label">Reset maintenance reminder</span>
                    </label>
                </div>

                <div class="card-footer">
                    <div class="btn-list">
                        <button type="submit" class="btn btn-primary btn-lg flex-fill flex-sm-grow-0">
                            Confirm Installed
                        </button>
                        <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="btn btn-ghost-secondary btn-lg">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </x-tabler.page>
</x-operations.app>
