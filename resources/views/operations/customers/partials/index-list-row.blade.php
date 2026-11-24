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

<tr data-collection-row data-customer-id="{{ $customer->id }}">
    <td>
        <a href="{{ $customerUrl }}" class="fw-bold text-reset">{{ $customer->name }}</a>
    </td>
    <td class="d-none d-lg-table-cell text-secondary">{{ $customer->display_phone ?: '—' }}</td>
    <td class="d-none d-xl-table-cell text-secondary text-truncate" style="max-width: 14rem">{{ $customer->email ?: '—' }}</td>
    <td class="d-none d-lg-table-cell text-secondary">{{ $customer->customer_type ?: '—' }}</td>
    <td class="text-nowrap">{{ $vehicleCount }}</td>
    <td>
        <x-tabler.status-badge :tone="$chipTone">{{ $chipLabel }}</x-tabler.status-badge>
    </td>
    <td class="d-none d-xl-table-cell text-secondary text-nowrap">{{ $customer->updated_at->timezone($displayTz)->format('M j, Y') }}</td>
</tr>
