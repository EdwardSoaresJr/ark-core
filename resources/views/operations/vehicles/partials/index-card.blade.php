@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle $vehicle */
    $displayTz = config('app.display_timezone');
    $activeRepairOrder = $vehicle->repairOrders->first();
    $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $customer = $vehicle->customer;
    $hubUrl = $customer
        ? route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $vehicle->id,
        ])
        : null;
    $customerUrl = $customer
        ? route('operations.customers.show', $customer)
        : null;
    $documentsUrl = $customer
        ? route('operations.customers.show', [
            'customer' => $customer,
            'tab' => 'documents',
        ])
        : null;
    $roShowUrl = ($activeRepairOrder && filled($activeRepairOrder->repair_order_id))
        ? route('operations.repair-orders.show', $activeRepairOrder)
        : null;
    $plateLabel = trim(collect([$vehicle->plate, $vehicle->plate_state])->filter()->implode(' '));
    $vinTail = filled($vehicle->vin) ? substr($vehicle->vin, -8) : null;
    $identityBits = collect([$plateLabel !== '' ? $plateLabel : null, $vinTail ? 'VIN '.$vinTail : null])
        ->filter()
        ->implode(' · ');
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : 'No open RO';
    $clockLabel = $activeRepairOrder
        ? 'RO #'.$activeRepairOrder->repair_order_id.' · Updated '.$activeRepairOrder->updated_at->timezone($displayTz)->format('M j, g:i A')
        : $vehicle->repair_orders_count.' '.Str::plural('RO', $vehicle->repair_orders_count).' on file · Updated '.$vehicle->updated_at->timezone($displayTz)->format('M j, g:i A');
    $indexActions = [
        [
            'href' => $hubUrl,
            'key' => 'vehicle',
            'label' => $vehicle->display_name,
        ],
        [
            'href' => $documentsUrl,
            'key' => 'documents',
            'label' => $customer ? 'Documents for '.$customer->name : 'Documents',
        ],
        [
            'href' => $customerUrl,
            'key' => 'customer',
            'label' => $customer?->name ?? 'Customer',
        ],
        [
            'href' => $roShowUrl,
            'key' => 'ro',
            'label' => $activeRepairOrder
                ? 'RO #'.$activeRepairOrder->repair_order_id
                : 'Repair order',
        ],
    ];
@endphp

<x-tabler.record-card :tone="$cardTone" :actions="$indexActions">
    @if ($hubUrl !== null)
        <a href="{{ $hubUrl }}" class="d-block fw-bold text-reset text-truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</a>
    @else
        <div class="fw-bold text-truncate" title="{{ $vehicle->display_name }}">{{ $vehicle->operational_identity }}</div>
    @endif
    <div class="d-flex flex-wrap gap-1 mt-1">
        <x-tabler.status-badge :tone="$cardTone">{{ $chipLabel }}</x-tabler.status-badge>
        <x-tabler.vehicle-identity-chip :vehicle="$vehicle" />
    </div>
    @if ($customerUrl !== null)
        <a href="{{ $customerUrl }}" class="d-block text-truncate mt-1">{{ $customer?->name ?? 'Unknown customer' }}</a>
    @else
        <div class="text-truncate text-secondary mt-1">{{ $customer?->name ?? 'Unknown customer' }}</div>
    @endif
    <div class="text-secondary text-truncate">{{ $identityBits !== '' ? $identityBits : 'No plate or VIN on file' }}</div>
    <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
</x-tabler.record-card>
