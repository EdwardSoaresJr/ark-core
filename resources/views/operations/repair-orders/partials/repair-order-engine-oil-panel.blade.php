@php
    use App\Ark\Operations\Maintenance\EngineOilPreparedIncludes;
    use App\Ark\Operations\Maintenance\MaintenanceServiceStatus;

    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Maintenance\MaintenanceService> $engineOilServices */
    $engineOilServices = $engineOilServices ?? collect();
@endphp

<section class="card mb-3" data-engine-oil-panel aria-labelledby="engine-oil-panel-title">
    <div class="card-header">
        <div>
            <div class="subheader">Maintenance</div>
            <h3 class="card-title" id="engine-oil-panel-title">Engine Oil Service</h3>
        </div>
        @if ($engineOilServices->isEmpty() && ! ($presentationOnly ?? false))
            <div class="card-actions">
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder) }}"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button type="submit" class="btn btn-primary btn-sm">
                        + Add Engine Oil Service
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="list-group list-group-flush">
        @foreach ($engineOilServices as $oilService)
            <div id="maintenance-engine-oil-{{ $oilService->id }}" class="list-group-item">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <div class="fw-medium">
                            {{ $oilService->kind->label() }}
                            <span class="fw-normal text-secondary">· {{ $oilService->status->value }}</span>
                        </div>
                        @if ($oilService->packageLine)
                            <div class="small text-secondary">
                                Sold {{ number_format($oilService->packageLine->unit_price_cents / 100, 2) }}
                                <span>(package)</span>
                            </div>
                        @endif
                    </div>
                    @if ($oilService->status !== MaintenanceServiceStatus::Cancelled)
                        <a
                            href="{{ route('operations.repair-orders.maintenance.confirm', [$repairOrder, $oilService]) }}"
                            class="btn btn-sm"
                        >
                            {{ $oilService->current_event_id ? 'Correct Installed' : 'Confirm Installed' }}
                        </a>
                    @endif
                </div>

                @if ($oilService->currentEvent)
                    <div class="alert alert-success small mt-2 mb-0">
                        <div class="fw-bold">Installed · Oil Service #{{ $oilService->currentEvent->service_sequence }}</div>
                        <ul class="mt-1 mb-0 ps-3" style="list-style: disc">
                            @foreach (EngineOilPreparedIncludes::installedBullets($oilService->currentEvent) as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                        @if ($oilService->currentEvent->service_mileage)
                            <div class="mt-1">@ {{ number_format($oilService->currentEvent->service_mileage) }} mi</div>
                        @endif
                    </div>
                @else
                    <div class="card card-sm bg-secondary-lt small mt-2">
                        <div class="card-body">
                            <div class="fw-bold">Includes</div>
                            <ul class="mt-1 mb-0 ps-3" style="list-style: disc">
                                @foreach (EngineOilPreparedIncludes::bullets($oilService) as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                            <div class="text-secondary mt-1">Not confirmed - print uses prepared values until Confirm Installed</div>
                        </div>
                    </div>
                @endif

                @if ($oilService->status !== MaintenanceServiceStatus::Cancelled)
                    <form
                        method="POST"
                        action="{{ route('operations.repair-orders.maintenance.extra-quarts.store', [$repairOrder, $oilService]) }}"
                        class="row g-2 align-items-end border-top mt-3 pt-3"
                    >
                        @csrf
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                        <div class="col">
                            <label class="form-label small mb-1" for="extra-quarts-{{ $oilService->id }}">Extra quarts</label>
                            <input
                                id="extra-quarts-{{ $oilService->id }}"
                                name="quarts"
                                type="number"
                                step="0.1"
                                min="0.1"
                                required
                                placeholder="1"
                                class="form-control form-control-sm"
                            >
                        </div>
                        <div class="col">
                            <label class="form-label small mb-1" for="extra-cost-{{ $oilService->id }}">Cost / qt</label>
                            <input
                                id="extra-cost-{{ $oilService->id }}"
                                name="cost_per_quart"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="8.50"
                                class="form-control form-control-sm"
                            >
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm">
                                Add at cost
                            </button>
                        </div>
                        <div class="col-12 form-hint mt-1">
                            Beyond package - Part line at cost. Does not change the package price or Installed history.
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    @if ($engineOilServices->isNotEmpty())
        <div class="card-footer small text-secondary">
            Oil sticker prints with the tech ticket (PRINT menu). Confirm Installed records history for the next visit.
        </div>
    @endif
</section>
