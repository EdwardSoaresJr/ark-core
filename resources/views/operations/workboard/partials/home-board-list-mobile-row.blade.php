@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListRow $row */
    $card = $row->card;
    $surface = $row->surface;
    $repairOrder = $card->repairOrder;
    $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
    $statusLabel = filled($surface->chip->label) ? $surface->chip->label : 'No Status';
    $statusTone = filled($surface->chip->statusColor)
        ? \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($surface->chip->statusColor)
        : ($surface->chip->tone ?? 'move');
    $builderUrl = $card->href.'#builder';
    $nextPresentation = \App\Ark\Operations\Workboard\JobBoardCardNextPresentation::forSurface($surface);
    $nextAction = in_array($nextPresentation['kind'], ['action', 'warning', 'watch'], true)
        ? $nextPresentation['label']
        : null;
    $moneyLabel = filled($row->moneyLabel) ? $row->moneyLabel : '$0';
    $isBalanceDue = $row->moneyCaption === 'due';
@endphp

<article
    class="card card-sm"
    data-job-board-row
    data-job-card
    data-job-board-mobile-for="{{ $repairOrder->repair_order_id }}"
    data-ro-id="{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $row->searchHaystack }}"
    data-home-tech-id="{{ $row->technicianId ?? '' }}"
    data-home-lane-key="{{ $row->laneKey }}"
    data-workboard-attention="{{ $surface->attention }}"
>
    <a href="{{ $builderUrl }}" class="card-body py-2 px-3 text-reset text-decoration-none">
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-bold" data-job-board-row-ro data-job-card-ro>RO #{{ $repairOrder->repair_order_id }}</span>
            <span @class(['ms-auto fw-bold ark-money text-nowrap small', 'text-danger' => $isBalanceDue]) data-job-card-total>{{ $moneyLabel }}</span>
            <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
        </div>
        <div class="fw-medium text-truncate mt-1" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</div>
        <div class="small text-secondary text-truncate" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</div>
        <div class="d-flex align-items-center gap-2 mt-2 small">
            <span class="text-truncate{{ $nextAction === null ? ' text-secondary' : '' }}" data-next-kind="{{ $nextPresentation['kind'] }}">{{ $nextAction ?? '—' }}</span>
            <span class="ms-auto text-secondary text-nowrap">{{ $row->updatedLabel }}</span>
        </div>
    </a>
</article>
