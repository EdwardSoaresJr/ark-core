@php
    use App\Ark\Operations\Maintenance\EngineOilPreparedIncludes;
    use App\Ark\Operations\Maintenance\MaintenanceServiceStatus;

    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Maintenance\MaintenanceService> $engineOilServices */
    $engineOilServices = $engineOilServices ?? collect();
@endphp

<section class="mb-4 rounded-md border border-slate-200 bg-white px-3 py-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Maintenance</p>
            <p class="text-sm font-medium text-slate-900">Engine Oil Service</p>
        </div>
        @if ($engineOilServices->isEmpty() && ! ($presentationOnly ?? false))
            <form
                method="POST"
                action="{{ route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder) }}"
                data-refresh-scope="worksheet"
                data-saving-label="Saving…"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">
                    + Add Engine Oil Service
                </button>
            </form>
        @endif
    </div>

    @foreach ($engineOilServices as $oilService)
        <div id="maintenance-engine-oil-{{ $oilService->id }}" class="mt-3 border-t border-slate-100 pt-3 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="font-medium text-slate-900">
                        {{ $oilService->kind->label() }}
                        <span class="font-normal text-slate-500">· {{ $oilService->status->value }}</span>
                    </p>
                    @if ($oilService->packageLine)
                        <p class="text-slate-600">
                            Sold {{ number_format($oilService->packageLine->unit_price_cents / 100, 2) }}
                            <span class="text-slate-400">(package)</span>
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($oilService->status !== MaintenanceServiceStatus::Cancelled)
                        <a
                            href="{{ route('operations.repair-orders.maintenance.confirm', [$repairOrder, $oilService]) }}"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50"
                        >
                            {{ $oilService->current_event_id ? 'Correct Installed' : 'Confirm Installed' }}
                        </a>
                    @endif
                </div>
            </div>

            @if ($oilService->currentEvent)
                <div class="mt-2 rounded bg-emerald-50 px-2 py-1.5 text-xs text-emerald-900">
                    <p class="font-semibold">Installed · Oil Service #{{ $oilService->currentEvent->service_sequence }}</p>
                    <ul class="mt-1 list-disc pl-4">
                        @foreach (EngineOilPreparedIncludes::installedBullets($oilService->currentEvent) as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                    @if ($oilService->currentEvent->service_mileage)
                        <p class="mt-1">@ {{ number_format($oilService->currentEvent->service_mileage) }} mi</p>
                    @endif
                </div>
            @else
                <div class="mt-2 rounded bg-slate-50 px-2 py-1.5 text-xs text-slate-700">
                    <p class="font-semibold">Includes</p>
                    <ul class="mt-1 list-disc pl-4">
                        @foreach (EngineOilPreparedIncludes::bullets($oilService) as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-1 text-slate-500">Not confirmed — print uses prepared values until Confirm Installed</p>
                </div>
            @endif

            @if ($oilService->status !== MaintenanceServiceStatus::Cancelled)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.maintenance.extra-quarts.store', [$repairOrder, $oilService]) }}"
                    class="mt-3 grid grid-cols-[1fr_1fr_auto] items-end gap-2 border-t border-slate-100 pt-3"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600" for="extra-quarts-{{ $oilService->id }}">Extra quarts</label>
                        <input
                            id="extra-quarts-{{ $oilService->id }}"
                            name="quarts"
                            type="number"
                            step="0.1"
                            min="0.1"
                            required
                            placeholder="1"
                            class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600" for="extra-cost-{{ $oilService->id }}">Cost / qt</label>
                        <input
                            id="extra-cost-{{ $oilService->id }}"
                            name="cost_per_quart"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            placeholder="8.50"
                            class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                        >
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50">
                        Add at cost
                    </button>
                    <p class="col-span-3 text-[11px] text-slate-500">
                        Beyond package — Part line at cost. Does not change the package price or Installed history.
                    </p>
                </form>
            @endif
        </div>
    @endforeach

    @if ($engineOilServices->isNotEmpty())
        <p class="mt-3 text-[11px] text-slate-500">
            Oil sticker prints with the tech ticket (PRINT menu). Confirm Installed records history for the next visit.
        </p>
    @endif
</section>
