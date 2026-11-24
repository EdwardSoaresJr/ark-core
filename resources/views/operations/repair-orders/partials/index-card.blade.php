@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder $repairOrder */
    $displayTz = config('app.display_timezone');
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;
    $roShowUrl = filled($repairOrder->repair_order_id)
        ? route('operations.repair-orders.show', $repairOrder)
        : null;
    $customerUrl = $customer
        ? route('operations.customers.show', $customer)
        : null;
    $vehicleUrl = ($customer && $vehicle)
        ? route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $vehicle->id,
        ])
        : null;
    $documentsUrl = $customer
        ? route('operations.customers.show', [
            'customer' => $customer,
            'tab' => 'documents',
        ])
        : null;
    $cardTone = $repairOrder->status->indexTone();
    $indexActions = [
        [
            'href' => $vehicleUrl,
            'key' => 'vehicle',
            'label' => $vehicle?->display_name ?? 'Vehicle',
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
            'label' => filled($repairOrder->repair_order_id) ? 'RO #'.$repairOrder->repair_order_id : 'Repair order',
        ],
    ];
    $openedLabel = 'Opened '.$repairOrder->displayOpenedAt()->timezone($displayTz)->format('M j, Y');
    if ($closedAt = $repairOrder->displayClosedAt()) {
        $clockLabel = $openedLabel.' · Closed '.$closedAt->timezone($displayTz)->format('M j, g:i A');
    } else {
        $clockLabel = $openedLabel.' · Updated '.$repairOrder->updated_at->timezone($displayTz)->format('M j, g:i A');
    }
@endphp

<x-tabler.record-card :tone="$cardTone" :actions="$indexActions">
    <div class="d-flex align-items-start gap-2">
        @if ($roShowUrl !== null)
            <a href="{{ $roShowUrl }}" class="fw-bold text-reset">RO #{{ $repairOrder->repair_order_id }}</a>
        @else
            <span class="fw-bold">RO #{{ $repairOrder->repair_order_id }}</span>
        @endif
        <x-tabler.status-badge :tone="$cardTone" class="ms-auto">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
    </div>
    @if ($vehicleUrl !== null)
        <a href="{{ $vehicleUrl }}" class="d-block text-truncate mt-1">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</a>
    @else
        <div class="text-truncate mt-1">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</div>
    @endif
    @if ($customerUrl !== null)
        <a href="{{ $customerUrl }}" class="d-block text-truncate">{{ $customer?->name ?? 'Unknown customer' }}</a>
    @else
        <div class="text-truncate text-secondary">{{ $customer?->name ?? 'Unknown customer' }}</div>
    @endif
    @if (filled($repairOrder->concern_summary))
        <div class="text-secondary text-truncate" title="{{ $repairOrder->concern_summary }}">{{ $repairOrder->concern_summary }}</div>
    @endif
    <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
</x-tabler.record-card>
