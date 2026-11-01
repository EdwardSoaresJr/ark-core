@php
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $displayTz = ShopDisplayTimezone::resolve();
    $priorVisits = $repairOrder->vehicle?->repairOrders ?? collect();
    $priorDeferredOrders = $priorVisits->filter(fn ($priorVisit) => $priorVisit->futureWorkCount() > 0)->values();
    $priorVehicleFutureWorkCount = (int) $priorDeferredOrders->sum(fn ($priorVisit) => $priorVisit->futureWorkCount());
    $priorVehicleFutureWorkCents = (int) $priorDeferredOrders->sum(fn ($priorVisit) => $priorVisit->futureWorkSubtotalCents());
    $hasDeferredOnCurrent = $repairOrder->futureWorkCount() > 0;
    $hasDeferredFromPrior = $priorVehicleFutureWorkCount > 0;
    $otherOpenRepairOrders = collect($customerCallContext?->openRepairOrders ?? [])
        ->filter(
            fn ($openRepairOrder) => (int) $openRepairOrder->repairOrder->repair_order_id !== (int) $repairOrder->repair_order_id
                && (int) $openRepairOrder->vehicle->id !== (int) $repairOrder->vehicle_id,
        )
        ->values();
@endphp

<div id="history-rail" class="d-flex flex-column gap-3" data-history-panel>
    {{-- The status history panel is shared with the worksheet and moves with it. --}}
    <x-operations.worksheet-island>
        @include('operations.repair-orders.partials.repair-order-lifecycle-panel', [
            'lifecyclePlacement' => 'history',
            'repairOrder' => $repairOrder,
        ])
    </x-operations.worksheet-island>

    <div>
        <h3 class="card-title mb-1">Vehicle History</h3>
        <p class="small text-secondary mb-0">Prior visits and deferred work for this vehicle - advisory context only. Calls and texts live on Comms.</p>
    </div>

    <section class="card card-sm" data-history-deferred>
        <div class="card-body">
            <div class="subheader mb-2">Deferred work to revisit</div>
            @if ($hasDeferredFromPrior || $hasDeferredOnCurrent)
                <div class="row g-2">
                    <div class="col-6">
                        <div class="small text-secondary">From prior visits</div>
                        <div class="h3 mb-0 ark-money">{{ $priorVehicleFutureWorkCount }}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-secondary">Retained value</div>
                        <div class="h3 mb-0 ark-money">${{ number_format($priorVehicleFutureWorkCents / 100, 2) }}</div>
                    </div>
                </div>
                @if ($hasDeferredOnCurrent)
                    <div class="alert alert-secondary mt-3 mb-0 fw-medium">
                        This RO: {{ $repairOrder->futureWorkSummary() }} · {{ $repairOrder->futureWorkNextAction() }}
                    </div>
                @endif
            @else
                <p class="small text-secondary mb-0">No deferred recommendations retained from earlier visits on this vehicle.</p>
            @endif
        </div>
        @if ($hasDeferredFromPrior || $hasDeferredOnCurrent)
            @if ($priorDeferredOrders->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach ($priorDeferredOrders->take(3) as $futureWorkOrder)
                        <a href="{{ route('operations.repair-orders.show', $futureWorkOrder) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div style="min-width: 0">
                                    <div class="fw-bold text-truncate">RO #{{ $futureWorkOrder->repair_order_id }} · {{ $futureWorkOrder->futureWorkSummary() }}</div>
                                    <div class="small text-secondary">{{ $futureWorkOrder->futureWorkNextAction() }}</div>
                                </div>
                                <div class="flex-shrink-0 fw-bold ark-money">${{ number_format($futureWorkOrder->futureWorkSubtotalCents() / 100, 2) }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </section>

    <section class="card card-sm" data-history-prior-visits>
        <div class="card-header">
            <h3 class="card-title">Prior visits on this vehicle</h3>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($priorVisits as $priorVisit)
                @php
                    $visitStamp = $priorVisit->displayClosedAt() ?? $priorVisit->displayOpenedAt();
                    $visitLabel = $priorVisit->displayClosedAt() ? 'Closed' : $priorVisit->status->label();
                @endphp
                <a href="{{ route('operations.repair-orders.show', $priorVisit) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        <span class="fw-bold">
                            RO #{{ $priorVisit->repair_order_id }}
                            <span class="fw-normal text-secondary">· {{ $visitLabel }}</span>
                        </span>
                        <span class="small text-secondary">{{ $visitStamp->timezone($displayTz)->format('M j, Y') }}</span>
                    </div>
                    <div class="small text-secondary text-truncate">{{ $priorVisit->concern_summary }}</div>
                    @if ($priorVisit->futureWorkCount() > 0)
                        <div class="small fw-medium text-orange">
                            {{ $priorVisit->futureWorkSummary() }} · {{ $priorVisit->futureWorkNextAction() }}
                        </div>
                    @endif
                </a>
            @empty
                <div class="list-group-item small text-secondary">First repair order recorded for this vehicle in ARK.</div>
            @endforelse
        </div>
    </section>

    @if ($otherOpenRepairOrders->isNotEmpty())
        <section class="card card-sm" data-history-other-open>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Other open work for customer</h3>
                    <p class="card-subtitle">Different vehicle - open only when the customer asks about another car.</p>
                </div>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($otherOpenRepairOrders as $otherOpenRepairOrder)
                    <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="small fw-medium" style="min-width: 0">
                            {{ $otherOpenRepairOrder->vehicle->display_name }}
                            <span class="text-secondary">· RO #{{ $otherOpenRepairOrder->repairOrder->repair_order_id }} · {{ $otherOpenRepairOrder->repairOrder->status->label() }}</span>
                        </span>
                        <a href="{{ route('operations.repair-orders.show', $otherOpenRepairOrder->repairOrder) }}" class="btn btn-sm flex-shrink-0">Open RO</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
