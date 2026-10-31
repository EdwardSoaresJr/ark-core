<div class="mb-3">
    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2">
        <h2 class="subheader mb-0">Recommended Work</h2>
        <span id="worksheet-count" class="small text-secondary" data-continuity-region="worksheet-count">{{ $repairOrder->concerns->count() }} scopes · {{ $repairOrder->lines->count() }} lines</span>
    </div>

    @include('operations.repair-orders.partials.repair-order-estimate-instrument-strip', [
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'estimateInstruments' => $estimateInstruments ?? null,
    ])
</div>
