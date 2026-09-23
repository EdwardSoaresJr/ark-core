<div id="financial-tab" class="ops-financial-tab">
    @if ($financial['showFinancialRail'] ?? false)
        @include('operations.repair-orders.partials.financial-rail')
    @else
        <p class="px-3 py-3 text-sm font-semibold text-slate-600">No closeout on this repair order.</p>
    @endif
</div>
