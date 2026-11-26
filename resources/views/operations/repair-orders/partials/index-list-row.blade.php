@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder $repairOrder */
    /** @var array<int, array{label: string, caption: ?string, collectible: bool}>|null $indexMoney */
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
    $vehicleLabel = $vehicle?->display_name ?? 'Unknown vehicle';
    $customerName = trim((string) ($customer?->name ?? '')) !== '' ? $customer->name : 'Unknown customer';
    $openedLabel = $repairOrder->displayOpenedAt()->timezone($displayTz)->format('M j, Y');
    $updatedLabel = $repairOrder->updated_at->timezone($displayTz)->format('M j, Y');
    $money = ($indexMoney ?? [])[$repairOrder->id] ?? null;
    $isCollectible = (bool) ($money['collectible'] ?? false);
    $moneyCaption = $money['caption'] ?? null;
@endphp

<x-tabler.hybrid-record
    :status-tone="$cardTone"
    data-collection-row
    data-ro-id="{{ $repairOrder->repair_order_id }}"
>
    <x-slot:identity>
        @if ($vehicleUrl !== null)
            <a href="{{ $vehicleUrl }}" class="ark-collection-hybrid-record__vehicle">{{ $vehicleLabel }}</a>
        @else
            <span class="ark-collection-hybrid-record__vehicle">{{ $vehicleLabel }}</span>
        @endif
        @if ($roShowUrl !== null)
            <a href="{{ $roShowUrl }}" class="ark-collection-hybrid-record__ro">RO #{{ $repairOrder->repair_order_id }}</a>
        @else
            <span class="ark-collection-hybrid-record__ro">RO #{{ $repairOrder->repair_order_id }}</span>
        @endif
        @if ($customerUrl !== null)
            <a href="{{ $customerUrl }}" class="ark-collection-hybrid-record__customer">{{ $customerName }}</a>
        @else
            <span class="ark-collection-hybrid-record__customer">{{ $customerName }}</span>
        @endif
    </x-slot:identity>

    <x-slot:middle>
        <div class="ark-collection-hybrid-record__concern" title="{{ filled($repairOrder->concern_summary) ? $repairOrder->concern_summary : null }}">
            <div class="ark-job-board-concern-stack">
                <div @class(['ark-job-board-concern-text', 'text-secondary' => ! filled($repairOrder->concern_summary)])>{{ filled($repairOrder->concern_summary) ? $repairOrder->concern_summary : '—' }}</div>
            </div>
        </div>
        <div class="ark-collection-hybrid-record__actions-row">
            <span class="ark-collection-hybrid-record__updated">Updated {{ $updatedLabel }}</span>
        </div>
    </x-slot:middle>

    <x-slot:workflow>
        <div class="ark-collection-hybrid-record__workflow-block">
            <div class="ark-collection-hybrid-record__field-label">Status</div>
            <x-tabler.status-badge :tone="$cardTone">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
        </div>
        <div class="ark-collection-hybrid-record__workflow-block">
            <div class="ark-collection-hybrid-record__field-label">Opened</div>
            <div class="ark-collection-hybrid-record__tech">{{ $openedLabel }}</div>
        </div>
    </x-slot:workflow>

    <x-slot:finance>
        @if ($money !== null)
            <div @class(['ark-job-board-money', 'ark-job-board-money--due' => $isCollectible])>
                <span class="ark-job-board-money__amount ark-money">{{ $money['label'] }}</span>
                @if (filled($moneyCaption))
                    <span class="ark-job-board-money__caption">{{ $moneyCaption }}</span>
                @endif
            </div>
        @else
            <div class="ark-job-board-money">
                <span class="ark-job-board-money__amount">{{ $openedLabel }}</span>
                <span class="ark-job-board-money__caption">Opened</span>
            </div>
        @endif
    </x-slot:finance>

    <x-slot:open>
        @if ($roShowUrl !== null)
            <a href="{{ $roShowUrl }}" class="ark-collection-hybrid-record__open" aria-label="Open RO #{{ $repairOrder->repair_order_id }}">Open <span aria-hidden="true">›</span></a>
        @endif
    </x-slot:open>
</x-tabler.hybrid-record>
