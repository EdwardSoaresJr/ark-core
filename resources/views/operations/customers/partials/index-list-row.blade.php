@php
    /** @var \App\Ark\Operations\Customers\Customer $customer */
    $displayTz = config('app.display_timezone');
    $activeRepairOrder = $customer->repairOrders->first();
    $hubUrl = route('operations.customers.show', $customer);
    $customerUrl = ($intakeMode ?? false)
        ? route('operations.intake.create', ['customer_id' => $customer->id])
        : $hubUrl;
    $vehicleCount = (int) ($customer->vehicles_count ?? $customer->vehicles->count());
    $openRoCount = $customer->open_repair_orders_count !== null ? (int) $customer->open_repair_orders_count : null;
    $chipTone = $activeRepairOrder ? $activeRepairOrder->status->indexTone() : 'move';
    $chipLabel = $activeRepairOrder
        ? $activeRepairOrder->statusDisplayLabel()
        : 'No open RO';
    $updatedLabel = $customer->updated_at->timezone($displayTz)->format('M j, Y');
@endphp

<x-tabler.hybrid-record
    data-collection-row
    data-customer-id="{{ $customer->id }}"
>
    <x-slot:identity>
        <a href="{{ $customerUrl }}" class="ark-collection-hybrid-record__vehicle">{{ $customer->name }}</a>
        @if (filled($customer->customer_type))
            <span class="ark-collection-hybrid-record__ro">{{ $customer->customer_type }}</span>
        @endif
    </x-slot:identity>

    <x-slot:middle>
        <div class="ark-collection-hybrid-record__meta">
            <span class="ark-collection-hybrid-record__meta-item">{{ $customer->display_phone ?: 'No phone' }}</span>
            @if (filled($customer->email))
                <span class="ark-collection-hybrid-record__meta-item">{{ $customer->email }}</span>
            @endif
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
            <span class="ark-job-board-money__amount">{{ $vehicleCount }}</span>
            <span class="ark-job-board-money__caption">{{ Str::plural('Vehicle', $vehicleCount) }}</span>
        </div>
        @if ($openRoCount !== null)
            <span class="ark-collection-hybrid-record__stat-caption">{{ $openRoCount }} open {{ Str::plural('RO', $openRoCount) }}</span>
        @endif
    </x-slot:finance>

    <x-slot:open>
        <a href="{{ $customerUrl }}" class="ark-collection-hybrid-record__open" aria-label="Open {{ $customer->name }}">{{ ($intakeMode ?? false) ? 'Start' : 'Open' }} <span aria-hidden="true">›</span></a>
    </x-slot:open>
</x-tabler.hybrid-record>
