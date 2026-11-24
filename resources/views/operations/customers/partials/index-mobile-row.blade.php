@php
    /** @var \App\Ark\Operations\Customers\Customer $customer */
    $displayTz = config('app.display_timezone');
    $activeRepairOrder = $customer->repairOrders->first();
    $hubUrl = route('operations.customers.show', $customer);
    $customerUrl = ($intakeMode ?? false)
        ? route('operations.intake.create', ['customer_id' => $customer->id])
        : $hubUrl;
    $vehicleCount = $customer->vehicles_count ?? $customer->vehicles->count();
    $chipTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : 'No open RO';
@endphp

<article class="card card-sm" data-collection-row data-customer-id="{{ $customer->id }}">
    <a href="{{ $customerUrl }}" class="card-body py-2 px-3 text-reset text-decoration-none">
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-bold text-truncate">{{ $customer->name }}</span>
            <x-tabler.status-badge :tone="$chipTone" class="ms-auto flex-shrink-0">{{ $chipLabel }}</x-tabler.status-badge>
        </div>
        <div class="small text-secondary text-truncate mt-1">{{ $customer->display_phone ?: 'No phone' }}</div>
        <div class="d-flex align-items-center gap-2 mt-1 small text-secondary">
            <span>{{ $vehicleCount }} {{ Str::plural('vehicle', $vehicleCount) }}</span>
            <span class="ms-auto text-nowrap">{{ $customer->updated_at->timezone($displayTz)->format('M j') }}</span>
        </div>
    </a>
</article>
