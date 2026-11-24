@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder $repairOrder */
    $displayTz = config('app.display_timezone');
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;
    $roShowUrl = filled($repairOrder->repair_order_id)
        ? route('operations.repair-orders.show', $repairOrder)
        : null;
    $customerUrl = $customer ? route('operations.customers.show', $customer) : null;
    $vehicleUrl = ($customer && $vehicle)
        ? route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $vehicle->id,
        ])
        : null;
    $cardTone = $repairOrder->status->indexTone();
@endphp

<tr data-collection-row data-ro-id="{{ $repairOrder->repair_order_id }}">
    <td class="text-nowrap">
        @if ($roShowUrl !== null)
            <a href="{{ $roShowUrl }}" class="fw-bold text-reset">RO #{{ $repairOrder->repair_order_id }}</a>
        @else
            <span class="fw-bold">RO #{{ $repairOrder->repair_order_id }}</span>
        @endif
    </td>
    <td>
        @if ($vehicleUrl !== null)
            <a href="{{ $vehicleUrl }}" class="text-reset text-truncate d-inline-block" style="max-width: 16rem">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</a>
        @else
            <span class="text-secondary">{{ $vehicle?->display_name ?? 'Unknown vehicle' }}</span>
        @endif
    </td>
    <td class="d-none d-lg-table-cell">
        @if ($customerUrl !== null)
            <a href="{{ $customerUrl }}" class="text-reset">{{ $customer?->name ?? 'Unknown customer' }}</a>
        @else
            <span class="text-secondary">Unknown customer</span>
        @endif
    </td>
    <td>
        <x-tabler.status-badge :tone="$cardTone">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
    </td>
    <td class="d-none d-xl-table-cell text-secondary text-nowrap">{{ $repairOrder->displayOpenedAt()->timezone($displayTz)->format('M j, Y') }}</td>
    <td class="d-none d-xl-table-cell text-secondary text-nowrap">{{ $repairOrder->updated_at->timezone($displayTz)->format('M j, Y') }}</td>
</tr>
