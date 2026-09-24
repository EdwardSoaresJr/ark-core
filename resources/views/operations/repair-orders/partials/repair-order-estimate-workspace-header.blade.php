<div class="ops-workspace-header ops-workspace-header--worksheet">
    <div class="ops-workspace-header__row">
        <p class="ops-eyebrow">Recommended Work</p>
        <p class="ops-worksheet-meta">{{ $repairOrder->concerns->count() }} scopes · {{ $repairOrder->lines->count() }} lines</p>
    </div>

    @include('operations.repair-orders.partials.repair-order-estimate-instrument-strip', [
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'estimateInstruments' => $estimateInstruments ?? null,
    ])
</div>
