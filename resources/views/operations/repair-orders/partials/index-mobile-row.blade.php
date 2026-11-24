@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder $repairOrder */
    $displayTz = config('app.display_timezone');
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;
    $roShowUrl = filled($repairOrder->repair_order_id)
        ? route('operations.repair-orders.show', $repairOrder)
        : '#';
    $cardTone = $repairOrder->status->indexTone();
@endphp

<article class="card card-sm" data-collection-row data-ro-id="{{ $repairOrder->repair_order_id }}">
    <a href="{{ $roShowUrl }}" class="card-body py-2 px-3 text-reset text-decoration-none">
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-bold">RO #{{ $repairOrder->repair_order_id }}</span>
            <x-tabler.status-badge :tone="$cardTone" class="ms-auto flex-shrink-0">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
        </div>
        <div class="fw-medium text-truncate mt-1">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</div>
        <div class="small text-secondary text-truncate">{{ $customer?->name ?? 'Unknown customer' }}</div>
        <div class="d-flex align-items-center gap-2 mt-1 small text-secondary">
            <span class="text-truncate">{{ filled($repairOrder->concern_summary) ? $repairOrder->concern_summary : '—' }}</span>
            <span class="ms-auto text-nowrap">{{ $repairOrder->updated_at->timezone($displayTz)->format('M j') }}</span>
        </div>
    </a>
</article>
