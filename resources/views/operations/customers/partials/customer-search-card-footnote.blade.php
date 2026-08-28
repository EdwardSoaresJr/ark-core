@php
    $customerRecord = $customer ?? $result;
    $activeRepairOrder = $customerRecord->repairOrders->first();
    $vehicleCount = $customerRecord->vehicles->count();
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
        {{ $vehicleCount }} {{ Str::plural('vehicle', $vehicleCount) }}
        <span class="ops-ro-sep">·</span>
        no active RO
    @endif
</p>
