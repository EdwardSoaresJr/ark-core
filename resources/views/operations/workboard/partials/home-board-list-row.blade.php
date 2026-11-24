@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListRow $row */
    $card = $row->card;
    $surface = $row->surface;
    $repairOrder = $card->repairOrder;
    $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
    $customerPhone = filled($surface->customerPhone) ? $surface->customerPhone : null;
    $statusLabel = filled($surface->chip->label) ? $surface->chip->label : 'No Status';
    $statusTone = filled($surface->chip->statusColor)
        ? \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($surface->chip->statusColor)
        : ($surface->chip->tone ?? 'move');
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
    $isBalanceDue = $row->moneyCaption === 'due';
    $moneyLabel = filled($row->moneyLabel) ? $row->moneyLabel : '$0';
    $markClasses = fn (string $state): string => match ($state) {
        'ready' => 'bg-secondary-lt',
        'sent' => 'bg-azure-lt',
        'engaged' => 'bg-azure text-white',
        'attention' => 'bg-orange text-white',
        default => 'bg-transparent text-secondary',
    };
@endphp

<tr
    id="ops-list-ro-{{ $repairOrder->repair_order_id }}"
    data-job-board-row
    data-job-card
    data-ro-id="{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $row->searchHaystack }}"
    data-home-tech-id="{{ $row->technicianId ?? '' }}"
    data-home-lane-key="{{ $row->laneKey }}"
    data-workboard-attention="{{ $surface->attention }}"
    @if ($surface->waitingOnCustomerDecision) data-workboard-decision="1" @endif
>
    <td>
        <a href="{{ $builderUrl }}" class="fw-bold text-reset text-nowrap" data-job-board-row-ro data-job-card-ro>RO #{{ $repairOrder->repair_order_id }}</a>
    </td>
    <td style="max-width: 11rem;">
        <div class="text-truncate">
            @if ($surface->customerHubUrl)
                <a href="{{ $surface->customerHubUrl }}" class="text-reset" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
            @else
                <span data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
            @endif
        </div>
        @if ($customerPhone)
            <div class="small text-secondary text-truncate">{{ $customerPhone }}</div>
        @endif
    </td>
    <td class="text-truncate" style="max-width: 11rem;">
        <a href="{{ $builderUrl }}" class="text-reset" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
    </td>
    <td class="text-truncate text-secondary" style="max-width: 12rem;" title="{{ $surface->concernLabel ?? $card->concernHeadline }}">
        {{ $surface->concernLabel ?? ($card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : '—') }}
    </td>
    <td>
        <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status data-job-card-status>
            <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
        </a>
    </td>
    <td class="text-nowrap small" data-job-board-row-technician>
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
    <td @class(['text-nowrap text-end fw-bold ark-money', 'text-danger' => $isBalanceDue]) data-job-board-row-total data-job-card-total>
        {{ $moneyLabel }}
        @if ($isBalanceDue)
            <span class="small fw-normal">due</span>
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
    <td data-job-board-row-activity data-job-card-activity>
        <div class="d-flex gap-1" role="group" aria-label="Activity">
            @foreach ($row->activityMarks as $mark)
                <span
                    class="avatar avatar-xs rounded {{ $markClasses($mark->state->value) }}"
                    data-job-card-mark="{{ $mark->key }}"
                    data-job-card-mark-state="{{ $mark->state->value }}"
                    role="img"
                    tabindex="-1"
                    title="{{ $mark->tooltip }}"
                    aria-label="{{ $mark->tooltip }}"
                    data-bs-toggle="tooltip"
                    data-bs-trigger="hover focus"
                >
                    @include('operations.workboard.partials.activity-mark-icon', [
                        'key' => $mark->key,
                        'filled' => $mark->active,
                        'badge' => $mark->badge,
                    ])
                </span>
            @endforeach
        </div>
    </td>
    <td class="text-secondary text-nowrap small" data-job-board-row-updated data-job-card-clock>{{ $row->updatedLabel }}</td>
    <td>
        <a href="{{ $builderUrl }}" class="btn btn-sm btn-ghost-secondary" aria-label="Open RO #{{ $repairOrder->repair_order_id }}">
            <x-tabler.icon name="chevron-right" />
        </a>
    </td>
</tr>
