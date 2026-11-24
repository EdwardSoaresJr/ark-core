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
    $moneyLabel = filled($row->moneyLabel) ? $row->moneyLabel : '$0';
    $isBalanceDue = $row->moneyCaption === 'due';
    $concernFull = trim((string) (
        \App\Ark\Operations\RepairOrders\RepairOrderConcernText::primary($repairOrder)
        ?: $surface->concernLabel
        ?: ($card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : '')
    ));
    $concernText = $concernFull !== '' ? $concernFull : '—';
    $attentionLabel = match ($surface->attention) {
        'critical' => $surface->exceptionLabel ?: 'Critical',
        'attention' => $surface->exceptionLabel ?: 'Needs attention',
        'watch' => $surface->exceptionLabel ?: 'Watch',
        default => $surface->exceptionLabel,
    };
    $activityStrip = \App\Ark\Operations\Workboard\JobBoardActivityPresentation::forList($row->activityMarks);
    // Watch stays visually neutral like List (muted text only). Accent strip is for actionable states.
    $attentionColor = match ($surface->attention) {
        'attention' => 'yellow',
        'critical' => 'red',
        default => null,
    };
@endphp

<article
    id="ops-card-ro-{{ $repairOrder->repair_order_id }}"
    class="card card-sm ark-job-board-card h-100"
    data-job-board-card
    data-job-card
    data-job-board-row
    data-ro-id="{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $row->searchHaystack }}"
    data-home-tech-id="{{ $row->technicianId ?? '' }}"
    data-home-lane-key="{{ $row->laneKey }}"
    data-workboard-attention="{{ $surface->attention }}"
    @if ($surface->waitingOnCustomerDecision) data-workboard-decision="1" @endif
>
    @if ($attentionColor)
        <div class="card-status-start bg-{{ $attentionColor }}"></div>
    @endif
    <div class="card-body p-2 d-flex flex-column gap-1">
        <div class="d-flex align-items-baseline gap-2">
            <a href="{{ $builderUrl }}" class="fw-bold text-reset text-nowrap" data-job-board-row-ro data-job-card-ro>RO #{{ $repairOrder->repair_order_id }}</a>
            <span @class(['ms-auto fw-bold ark-money text-nowrap', 'text-danger' => $isBalanceDue]) data-job-card-total>
                {{ $moneyLabel }}
                @if ($isBalanceDue)
                    <span class="small fw-normal">due</span>
                @endif
            </span>
        </div>
        <div class="small text-truncate" data-job-board-row-customer-vehicle>
            @if ($surface->customerHubUrl)
                <a href="{{ $surface->customerHubUrl }}" class="text-reset" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
            @else
                <span data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
            @endif
            <span class="text-secondary" aria-hidden="true"> · </span>
            <a href="{{ $builderUrl }}" class="text-secondary text-decoration-none" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
        </div>
        <div class="small ark-job-board-concern-text" data-job-board-row-concern>{{ $concernText }}</div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status data-job-card-status>
                <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
            </a>
            @if (filled($row->technicianName))
                <span class="small text-secondary text-truncate" data-job-board-row-technician>{{ $row->technicianName }}</span>
            @endif
            <span class="ms-auto small text-secondary text-nowrap" data-job-card-clock>{{ $row->updatedLabel }}</span>
        </div>
        <div class="small" data-job-board-row-attention data-attention="{{ $surface->attention }}">
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
        </div>
        <div class="pt-1" data-job-board-row-activity data-job-card-activity>
            @if ($activityStrip['visible'] === [])
                <span class="text-secondary small" data-job-board-activity-empty>—</span>
            @else
                <div class="d-inline-flex align-items-center flex-nowrap ark-job-board-activity" role="group" aria-label="Activity">
                    @foreach ($activityStrip['visible'] as $mark)
                        @php
                            $markHref = \App\Ark\Operations\Workboard\JobBoardActivityPresentation::destinationFor($mark->key, $card->href);
                        @endphp
                        @if ($markHref)
                            <a
                                href="{{ $markHref }}"
                                class="ark-job-board-activity-mark"
                                data-job-card-mark="{{ $mark->key }}"
                                data-job-card-mark-state="{{ $mark->state->value }}"
                                data-job-board-activity-mark
                                data-job-board-activity-link
                                role="link"
                                title="{{ $mark->tooltip }}"
                                aria-label="{{ $mark->tooltip }}"
                            >
                                @include('operations.workboard.partials.activity-mark-icon', [
                                    'key' => $mark->key,
                                    'filled' => true,
                                    'badge' => $mark->badge,
                                ])
                            </a>
                        @else
                            <span
                                class="ark-job-board-activity-mark"
                                data-job-card-mark="{{ $mark->key }}"
                                data-job-card-mark-state="{{ $mark->state->value }}"
                                data-job-board-activity-mark
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
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</article>
