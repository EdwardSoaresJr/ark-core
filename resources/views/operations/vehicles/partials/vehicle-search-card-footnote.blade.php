@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle $vehicle */
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder|null $activeRepairOrder */
@endphp

<p class="ops-ro-footnote">
    @if ($activeRepairOrder)
        <a
            href="{{ route('operations.repair-orders.show', $activeRepairOrder) }}"
            class="ops-ro-footnote-link"
            aria-label="Open RO #{{ $activeRepairOrder->repair_order_id }}"
        >
            <span class="tabular-nums">RO #{{ $activeRepairOrder->repair_order_id }}</span>
        </a>
        <span class="ops-ro-sep">·</span>
        {{ $activeRepairOrder->status->label() }}
    @else
        {{ $vehicle->repair_orders_count }} {{ Str::plural('RO', $vehicle->repair_orders_count) }} on file
        <span class="ops-ro-sep">·</span>
        no active RO
    @endif
</p>
