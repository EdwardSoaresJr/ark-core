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
    $attentionLabel = match ($surface->attention) {
        'critical' => $surface->exceptionLabel ?: 'Critical',
        'attention' => $surface->exceptionLabel ?: 'Needs attention',
        'watch' => $surface->exceptionLabel ?: 'Watch',
        default => $surface->exceptionLabel,
    };
    $builderUrl = $card->href.'#builder';
    $isBalanceDue = $row->moneyCaption === 'due';
    $moneyLabel = filled($row->moneyLabel) ? $row->moneyLabel : '$0';
    $markClasses = fn (string $state): string => match ($state) {
        'ready' => 'bg-secondary-lt',
        'sent' => 'bg-azure-lt',
        'engaged' => 'bg-azure text-white',
        'attention' => 'bg-orange text-white',
        default => 'bg-transparent text-secondary',
    };
    $concernFull = trim((string) (
        $repairOrder->concern_summary
        ?: $surface->concernLabel
        ?: ($card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : '')
    ));
    $concernText = $concernFull !== '' ? $concernFull : '—';
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
    <td class="ark-job-board-col-customer-vehicle" data-job-board-row-customer-vehicle>
        <div class="text-truncate">
            @if ($surface->customerHubUrl)
                <a href="{{ $surface->customerHubUrl }}" class="text-reset" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
            @else
                <span data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
            @endif
        </div>
        <div class="small text-secondary text-truncate">
            <a href="{{ $builderUrl }}" class="text-reset" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
        </div>
    </td>
    <td
        class="text-body ark-job-board-col-concern"
        data-job-board-row-concern
        title="{{ $concernFull !== '' ? $concernFull : null }}"
    >
        <span class="ark-job-board-concern-text">{{ $concernText }}</span>
    </td>
    <td>
        <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status data-job-card-status>
            <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
        </a>
    </td>
    <td class="text-nowrap small" data-job-board-row-technician>
        {{ $row->technicianName ?? '—' }}
    </td>
    <td @class(['text-nowrap text-end fw-bold ark-money', 'text-danger' => $isBalanceDue]) data-job-board-row-total data-job-card-total>
        {{ $moneyLabel }}
        @if ($isBalanceDue)
            <span class="small fw-normal">due</span>
        @endif
    </td>
    <td class="small" data-job-board-row-attention data-attention="{{ $surface->attention }}">
        @if ($attentionLabel)
            <span
                @class([
                    'ark-job-board-attention',
                    'text-danger fw-semibold' => $surface->attention === 'critical',
                    'text-warning fw-semibold' => $surface->attention === 'attention',
                    'text-secondary' => $surface->attention === 'watch',
                ])
                data-attention-tone="{{ $surface->attention }}"
            >{{ $attentionLabel }}</span>
        @else
            <span class="text-secondary">—</span>
        @endif
    </td>
    <td class="text-nowrap" data-job-board-row-activity data-job-card-activity>
        @php
            $activeMarks = array_values(array_filter(
                $row->activityMarks,
                static fn ($mark) => $mark->active,
            ));
        @endphp
        @if ($activeMarks === [])
            <span class="text-secondary">—</span>
        @else
            <div class="d-inline-flex gap-1 ark-job-board-activity" role="group" aria-label="Activity">
                @foreach ($activeMarks as $mark)
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
                            'filled' => true,
                            'badge' => $mark->badge,
                        ])
                    </span>
                @endforeach
            </div>
        @endif
    </td>
    <td class="text-secondary text-nowrap small" data-job-board-row-updated data-job-card-clock>{{ $row->updatedLabel }}</td>
    <td>
        <a href="{{ $builderUrl }}" class="btn btn-sm btn-ghost-secondary" aria-label="Open RO #{{ $repairOrder->repair_order_id }}">
            <x-tabler.icon name="chevron-right" />
        </a>
    </td>
</tr>
