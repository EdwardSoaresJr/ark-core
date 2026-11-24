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
        : null;
    $customerUrl = $customer ? route('operations.customers.show', $customer) : null;
    $plateLabel = trim(collect([$vehicle->plate, $vehicle->plate_state])->filter()->implode(' '));
    $vinTail = filled($vehicle->vin) ? substr((string) $vehicle->vin, -8) : null;
    $mileage = $vehicle->legacyOdometerReading();
    $chipTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : 'No open RO';
@endphp

<tr data-collection-row data-vehicle-id="{{ $vehicle->id }}">
    <td>
        @if ($hubUrl !== null)
            <a href="{{ $hubUrl }}" class="fw-bold text-reset">{{ $vehicle->operational_identity }}</a>
        @else
            <span class="fw-bold">{{ $vehicle->operational_identity }}</span>
        @endif
    </td>
    <td>
        @if ($customerUrl !== null)
            <a href="{{ $customerUrl }}" class="text-reset">{{ $customer?->name ?? 'Unknown customer' }}</a>
        @else
            <span class="text-secondary">Unknown customer</span>
        @endif
    </td>
    <td class="d-none d-lg-table-cell text-secondary text-nowrap">{{ $plateLabel !== '' ? $plateLabel : '—' }}</td>
    <td class="d-none d-xl-table-cell text-secondary font-monospace">{{ $vinTail ? '…'.$vinTail : '—' }}</td>
    <td><x-tabler.status-badge :tone="$chipTone">{{ $chipLabel }}</x-tabler.status-badge></td>
    <td class="d-none d-xl-table-cell text-secondary text-nowrap">{{ $mileage !== null ? number_format($mileage).' mi' : '—' }}</td>
    <td class="d-none d-xl-table-cell text-secondary text-nowrap">{{ $vehicle->updated_at->timezone($displayTz)->format('M j, Y') }}</td>
</tr>
