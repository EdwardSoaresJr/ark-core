@php
    /** @var \App\Ark\Operations\Vehicles\Vehicle $vehicle */
    $displayTz = config('app.display_timezone');
    // Only a currently open RO is surfaced; historical ROs are never treated as active work.
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
    $updatedLabel = $vehicle->updated_at->timezone($displayTz)->format('M j, Y');
@endphp

<x-tabler.hybrid-record
    data-collection-row
    data-vehicle-id="{{ $vehicle->id }}"
>
    <x-slot:identity>
        @if ($hubUrl !== null)
            <a href="{{ $hubUrl }}" class="ark-collection-hybrid-record__vehicle">{{ $vehicle->operational_identity }}</a>
        @else
            <span class="ark-collection-hybrid-record__vehicle">{{ $vehicle->operational_identity }}</span>
        @endif
        @if ($customerUrl !== null)
            <a href="{{ $customerUrl }}" class="ark-collection-hybrid-record__customer">{{ $customer?->name ?? 'Unknown customer' }}</a>
        @else
            <span class="ark-collection-hybrid-record__customer">Unknown customer</span>
        @endif
    </x-slot:identity>

    <x-slot:middle>
        <div class="ark-collection-hybrid-record__meta">
            <span class="ark-collection-hybrid-record__meta-item">{{ $plateLabel !== '' ? $plateLabel : 'No plate' }}</span>
            <span class="ark-collection-hybrid-record__meta-item is-mono">{{ $vinTail ? 'VIN …'.$vinTail : 'No VIN' }}</span>
        </div>
        <div class="ark-collection-hybrid-record__actions-row">
            <span class="ark-collection-hybrid-record__updated">Updated {{ $updatedLabel }}</span>
        </div>
    </x-slot:middle>

    <x-slot:workflow>
        <div class="ark-collection-hybrid-record__workflow-block">
            <div class="ark-collection-hybrid-record__field-label">Open work</div>
            <x-tabler.status-badge :tone="$chipTone">{{ $chipLabel }}</x-tabler.status-badge>
        </div>
    </x-slot:workflow>

    <x-slot:finance>
        <div class="ark-job-board-money">
            <span class="ark-job-board-money__amount">{{ $mileage !== null ? number_format($mileage) : '—' }}</span>
            <span class="ark-job-board-money__caption">{{ $mileage !== null ? 'Miles' : 'Mileage' }}</span>
        </div>
    </x-slot:finance>

    <x-slot:open>
        @if ($hubUrl !== null)
            <a href="{{ $hubUrl }}" class="ark-collection-hybrid-record__open" aria-label="Open {{ $vehicle->operational_identity }}">Open <span aria-hidden="true">›</span></a>
        @endif
    </x-slot:open>
</x-tabler.hybrid-record>
