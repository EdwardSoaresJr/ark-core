@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle $vehicle */
    $displayTz = config('app.display_timezone');
    $activeRepairOrder = $vehicle->repairOrders->first();
    $customer = $vehicle->customer;
    $hubUrl = $customer
        ? route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $vehicle->id,
        ])
        : '#';
    $chipTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : 'No open RO';
    $plateLabel = trim(collect([$vehicle->plate, $vehicle->plate_state])->filter()->implode(' '));
@endphp

<article class="card card-sm" data-collection-row data-vehicle-id="{{ $vehicle->id }}">
    <a href="{{ $hubUrl }}" class="card-body py-2 px-3 text-reset text-decoration-none">
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-bold text-truncate">{{ $vehicle->operational_identity }}</span>
            <x-tabler.status-badge :tone="$chipTone" class="ms-auto flex-shrink-0">{{ $chipLabel }}</x-tabler.status-badge>
        </div>
        <div class="small text-secondary text-truncate mt-1">{{ $customer?->name ?? 'Unknown customer' }}</div>
        <div class="d-flex align-items-center gap-2 mt-1 small text-secondary">
            <span class="text-truncate">{{ $plateLabel !== '' ? $plateLabel : 'No plate' }}</span>
            <span class="ms-auto text-nowrap">{{ $vehicle->updated_at->timezone($displayTz)->format('M j') }}</span>
        </div>
    </a>
</article>
