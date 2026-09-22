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

<div id="history-rail" class="ops-review-rail-tab-panel divide-y divide-slate-100 text-sm">
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Vehicle History</p>
        <p class="ops-meta mt-0.5">Prior visits and deferred work for this vehicle — advisory context only. Calls and texts live on Comms.</p>
    </div>

    <section class="px-3 py-2.5">
        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Deferred work to revisit</p>
        @if ($hasDeferredFromPrior || $hasDeferredOnCurrent)
            <div class="mt-2 grid grid-cols-2 gap-px bg-slate-200">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">From prior visits</p>
                    <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $priorVehicleFutureWorkCount }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Retained value</p>
                    <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">${{ number_format($priorVehicleFutureWorkCents / 100, 2) }}</p>
                </div>
            </div>
            @if ($hasDeferredOnCurrent)
                <p class="mt-2 border-l-4 border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold leading-5 text-slate-700">
                    This RO: {{ $repairOrder->futureWorkSummary() }} · {{ $repairOrder->futureWorkNextAction() }}
                </p>
            @endif
            @foreach ($priorDeferredOrders->take(3) as $futureWorkOrder)
                <a href="{{ route('operations.repair-orders.show', $futureWorkOrder) }}" class="mt-2 block border border-slate-200 bg-white px-3 py-2 hover:border-slate-300">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-950">RO #{{ $futureWorkOrder->repair_order_id }} · {{ $futureWorkOrder->futureWorkSummary() }}</p>
                            <p class="mt-0.5 text-xs leading-4 text-slate-500">{{ $futureWorkOrder->futureWorkNextAction() }}</p>
                        </div>
                        <p class="shrink-0 font-black tabular-nums text-slate-950">${{ number_format($futureWorkOrder->futureWorkSubtotalCents() / 100, 2) }}</p>
                    </div>
                </a>
            @endforeach
        @else
            <p class="mt-1.5 text-xs leading-4 text-slate-500">No deferred recommendations retained from earlier visits on this vehicle.</p>
        @endif
    </section>

    <section class="px-3 py-2.5">
        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Prior visits on this vehicle</p>
        @forelse ($priorVisits as $priorVisit)
            @php
                $visitStamp = $priorVisit->displayClosedAt() ?? $priorVisit->displayOpenedAt();
                $visitLabel = $priorVisit->displayClosedAt() ? 'Closed' : $priorVisit->status->label();
            @endphp
            <a href="{{ route('operations.repair-orders.show', $priorVisit) }}" class="mt-2 block border border-slate-200 bg-white px-3 py-2 hover:border-slate-300">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-bold text-slate-950">
                        RO #{{ $priorVisit->repair_order_id }}
                        <span class="font-semibold text-slate-500">· {{ $visitLabel }}</span>
                    </p>
                    <p class="text-[11px] font-semibold text-slate-400">{{ $visitStamp->timezone($displayTz)->format('M j, Y') }}</p>
                </div>
                <p class="mt-1 line-clamp-2 text-xs leading-4 text-slate-600">{{ $priorVisit->concern_summary }}</p>
                @if ($priorVisit->futureWorkCount() > 0)
                    <p class="mt-1 text-[11px] font-semibold text-amber-800">
                        {{ $priorVisit->futureWorkSummary() }} · {{ $priorVisit->futureWorkNextAction() }}
                    </p>
                @endif
            </a>
        @empty
            <p class="mt-1.5 text-xs leading-4 text-slate-500">First repair order recorded for this vehicle in ARK.</p>
        @endforelse
    </section>

    @if ($otherOpenRepairOrders->isNotEmpty())
        <section class="px-3 py-2.5">
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Other open work for customer</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Different vehicle — open only when the customer asks about another car.</p>
            @foreach ($otherOpenRepairOrders as $otherOpenRepairOrder)
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border border-slate-200 bg-slate-50/80 px-3 py-2">
                    <p class="min-w-0 text-xs font-semibold text-slate-700">
                        {{ $otherOpenRepairOrder->vehicle->display_name }}
                        <span class="text-slate-500">· RO #{{ $otherOpenRepairOrder->repairOrder->repair_order_id }} · {{ $otherOpenRepairOrder->repairOrder->status->label() }}</span>
                    </p>
                    <a href="{{ route('operations.repair-orders.show', $otherOpenRepairOrder->repairOrder) }}" class="ops-page-link shrink-0">Open RO</a>
                </div>
            @endforeach
        </section>
    @endif
</div>
