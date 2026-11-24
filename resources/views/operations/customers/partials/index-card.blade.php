@php
    /** @var \App\Ark\Operations\Customers\Customer $customer */
    $displayTz = config('app.display_timezone');
    $activeRepairOrder = $customer->repairOrders->first();
    $primaryVehicle = $customer->vehicles->first();
    $cardTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $hubUrl = route('operations.customers.show', $customer);
    $customerUrl = ($intakeMode ?? false)
        ? route('operations.intake.create', ['customer_id' => $customer->id])
        : $hubUrl;
    $vehicleUrl = $primaryVehicle
        ? route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $primaryVehicle->id,
        ])
        : null;
    $documentsUrl = route('operations.customers.show', [
        'customer' => $customer,
        'tab' => 'documents',
    ]);
    $roShowUrl = ($activeRepairOrder && filled($activeRepairOrder->repair_order_id))
        ? route('operations.repair-orders.show', $activeRepairOrder)
        : null;
    $customerTag = trim((string) ($customer->customer_type ?: 'Retail'));
    $hideDefaultRetail = ($intakeMode ?? false) && $customerTag === 'Retail';
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : ($hideDefaultRetail ? 'No open RO' : $customerTag);
    $vehicleCount = $customer->vehicles_count ?? $customer->vehicles->count();
    $clockLabel = $activeRepairOrder
        ? 'RO #'.$activeRepairOrder->repair_order_id.' · Updated '.$activeRepairOrder->updated_at->timezone($displayTz)->format('M j, g:i A')
        : $vehicleCount.' '.Str::plural('vehicle', $vehicleCount).' · Updated '.$customer->updated_at->timezone($displayTz)->format('M j, g:i A');
    $indexActions = [
        [
            'href' => $vehicleUrl,
            'key' => 'vehicle',
            'label' => $primaryVehicle?->display_name ?? 'Vehicle',
        ],
        [
            'href' => $documentsUrl,
            'key' => 'documents',
            'label' => 'Documents for '.$customer->name,
        ],
        [
            'href' => $customerUrl,
            'key' => 'customer',
            'label' => $customer->name,
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
    <div class="d-flex align-items-start gap-2">
        <a href="{{ $customerUrl }}" class="fw-bold text-reset text-truncate" title="{{ $customer->name }}">{{ $customer->name }}</a>
        <x-tabler.status-badge :tone="$cardTone" class="ms-auto flex-shrink-0">{{ $chipLabel }}</x-tabler.status-badge>
    </div>
    @if ($vehicleUrl !== null)
        <a href="{{ $vehicleUrl }}" class="d-block text-truncate mt-1">{{ $primaryVehicle?->display_name ?? 'Unknown vehicle' }}</a>
    @else
        <div class="text-truncate text-secondary mt-1">{{ $primaryVehicle?->display_name ?? 'No vehicle on file' }}</div>
    @endif
    <div class="text-secondary">{{ $customer->display_phone ?: 'No phone' }}</div>
    <div class="text-secondary small mt-1 mt-md-2">{{ $clockLabel }}</div>
</x-tabler.record-card>
