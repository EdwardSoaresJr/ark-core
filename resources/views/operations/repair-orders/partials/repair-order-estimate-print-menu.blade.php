@php
    $printItems = App\Ark\Operations\RepairOrders\RepairOrderPrintMenuProjection::items(
        $repairOrder,
        $financial ?? null,
        auth()->user(),
    );
@endphp

<div class="dropdown" data-ro-print-menu>
    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Print" title="Print">
        <x-tabler.icon name="printer" />
        <span class="ark-ro-visit__action-label">Print</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end" role="menu">
        @include('operations.repair-orders.partials.repair-order-print-menu-items', [
            'printItems' => $printItems,
            'closeExpr' => '',
        ])
    </div>
</div>
