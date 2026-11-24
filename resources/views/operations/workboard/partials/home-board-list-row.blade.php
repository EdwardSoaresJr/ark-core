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
    $attentionRank = match ($surface->attention) {
        'critical' => 3,
        'attention' => 2,
        'watch' => 1,
        default => 0,
    };
    $attentionLabel = match ($surface->attention) {
        'critical' => $surface->exceptionLabel ?: 'Critical',
        'attention' => $surface->exceptionLabel ?: 'Needs attention',
        'watch' => $surface->exceptionLabel ?: 'Watch',
        default => $surface->exceptionLabel,
    };
    $builderUrl = $card->href.'#builder';
    $promiseToneClass = match ($surface->promiseTone) {
        'overdue' => 'text-danger',
        'today' => 'text-warning',
        default => 'text-secondary',
    };
@endphp

<tr
    id="ops-list-ro-{{ $repairOrder->repair_order_id }}"
    data-job-board-row
    data-ro-id="{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $row->searchHaystack }}"
    data-home-tech-id="{{ $row->technicianId ?? '' }}"
    data-home-lane-key="{{ $row->laneKey }}"
    data-sort-ro="{{ $repairOrder->repair_order_id }}"
    data-sort-customer="{{ strtolower($customerName) }}"
    data-sort-vehicle="{{ strtolower($card->vehicleLabel) }}"
    data-sort-status="{{ strtolower($statusLabel) }}"
    data-sort-technician="{{ strtolower((string) $row->technicianName) }}"
    data-sort-promised="{{ $surface->promiseLabel ? strtolower($surface->promiseLabel) : '' }}"
    data-sort-attention="{{ $attentionRank }}"
    data-sort-updated="{{ $row->updatedAtTimestamp }}"
    data-sort-pressure="{{ $card->pressureScore }}"
    x-show="cardMatches($el)"
    x-cloak
>
    <td>
        <a href="{{ $builderUrl }}" class="fw-bold text-reset text-nowrap" data-job-board-row-ro>RO #{{ $repairOrder->repair_order_id }}</a>
    </td>
    <td class="text-truncate" style="max-width: 10rem;">
        @if ($surface->customerHubUrl)
            <a href="{{ $surface->customerHubUrl }}" class="text-reset" data-job-board-row-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
        @else
            <span data-job-board-row-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
        @endif
    </td>
    <td class="text-truncate" style="max-width: 11rem;">
        <a href="{{ $builderUrl }}" class="text-reset" data-job-board-row-vehicle>{{ $card->vehicleLabel }}</a>
    </td>
    <td class="text-truncate text-secondary" style="max-width: 12rem;" title="{{ $surface->concernLabel ?? $card->concernHeadline }}">
        {{ $surface->concernLabel ?? ($card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : '—') }}
    </td>
    <td>
        <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status>
            <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
        </a>
    </td>
    <td class="text-nowrap" data-job-board-row-technician>
        {{ $row->technicianName ?? '—' }}
    </td>
    <td @class(['text-nowrap small', $promiseToneClass]) data-job-board-row-promised>
        {{ $surface->promiseLabel ?? '—' }}
    </td>
    <td class="text-nowrap small" data-job-board-row-parts>
        @if ($row->partsLabel)
            <span @class(['badge', $row->partsLabel === 'Ready' ? 'bg-success-lt' : 'bg-orange-lt'])>{{ $row->partsLabel }}</span>
        @else
            —
        @endif
    </td>
    <td class="small" data-job-board-row-attention>
        @if ($attentionLabel)
            <span @class([
                'text-danger' => $surface->attention === 'critical',
                'text-warning' => in_array($surface->attention, ['attention', 'watch'], true),
            ])>{{ $attentionLabel }}</span>
        @else
            <span class="text-secondary">—</span>
        @endif
    </td>
    <td class="text-secondary text-nowrap small" data-job-board-row-updated>{{ $row->updatedLabel }}</td>
    <td>
        <a href="{{ $builderUrl }}" class="btn btn-sm btn-ghost-secondary" aria-label="Open RO #{{ $repairOrder->repair_order_id }}">
            <x-tabler.icon name="chevron-right" />
        </a>
    </td>
</tr>
