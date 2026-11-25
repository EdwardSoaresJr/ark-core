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
    $concernLines = \App\Ark\Operations\RepairOrders\RepairOrderConcernText::lines($repairOrder, 2);
    if ($concernLines === []) {
        $fallback = trim((string) (
            $surface->concernLabel
            ?: ($card->concernHeadline !== 'No concern recorded' ? $card->concernHeadline : '')
        ));
        if ($fallback !== '') {
            $concernLines = [$fallback];
        }
    }
    $concernFull = $concernLines !== [] ? implode("\n", $concernLines) : '';
    $nextPresentation = \App\Ark\Operations\Workboard\JobBoardCardNextPresentation::forSurface($surface);
    $activityStrip = \App\Ark\Operations\Workboard\JobBoardActivityPresentation::forList($row->activityMarks);
    $attentionColor = match ($surface->attention) {
        'attention' => 'yellow',
        'critical' => 'red',
        default => null,
    };
@endphp

<article
    id="ops-card-ro-{{ $repairOrder->repair_order_id }}"
    class="card ark-job-board-card"
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
    <div class="card-body ark-job-board-card-body">
        <div class="ark-job-board-card-band ark-job-board-card-band--identity">
            <a href="{{ $builderUrl }}" class="fw-bold text-reset text-nowrap" data-job-board-row-ro data-job-card-ro>RO #{{ $repairOrder->repair_order_id }}</a>
            <span @class(['ms-auto fw-bold ark-money text-nowrap', 'text-danger' => $isBalanceDue]) data-job-card-total>
                {{ $moneyLabel }}
                @if ($isBalanceDue)
                    <span class="small fw-normal">due</span>
                @endif
            </span>
        </div>

        <div class="ark-job-board-card-band ark-job-board-card-band--party" data-job-board-row-customer-vehicle>
            <div class="ark-job-board-card-vehicle text-truncate">
                <a href="{{ $builderUrl }}" class="text-reset fw-semibold text-decoration-none" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
            </div>
            <div class="small text-secondary text-truncate">
                @if ($surface->customerHubUrl)
                    <a href="{{ $surface->customerHubUrl }}" class="text-secondary" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
                @else
                    <span data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
                @endif
            </div>
        </div>

        <div
            class="ark-job-board-card-band ark-job-board-card-band--concern"
            data-job-board-row-concern
            @if ($concernFull !== '') title="{{ $concernFull }}" @endif
        >
            @if ($concernLines === [])
                <span class="ark-job-board-concern-text text-secondary">—</span>
            @else
                <div class="ark-job-board-concern-stack">
                    @foreach ($concernLines as $line)
                        <div class="ark-job-board-concern-text{{ $loop->first ? '' : ' ark-job-board-concern-text--secondary' }}">{{ $line }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ark-job-board-card-band ark-job-board-card-band--state d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status data-job-card-status>
                <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
            </a>
            <span class="small text-secondary text-truncate" data-job-board-row-technician>
                {{ filled($row->technicianName) ? $row->technicianName : 'Unassigned' }}
            </span>
        </div>

        <div class="ark-job-board-card-band ark-job-board-card-band--next" data-job-board-row-attention data-attention="{{ $surface->attention }}">
            @if ($nextPresentation['kind'] === 'action')
                <span
                    class="ark-job-board-card-next text-body"
                    data-job-board-card-next
                    data-next-kind="action"
                >
                    <span class="text-secondary fw-normal">Next</span>
                    {{ $nextPresentation['label'] }}
                </span>
            @elseif ($nextPresentation['kind'] === 'warning')
                <span
                    @class([
                        'ark-job-board-attention fw-semibold',
                        'text-danger' => $surface->attention === 'critical',
                        'text-warning' => $surface->attention === 'attention',
                    ])
                    data-attention-tone="{{ $surface->attention }}"
                    data-next-kind="warning"
                >{{ $nextPresentation['label'] }}</span>
            @elseif ($nextPresentation['kind'] === 'watch')
                <span class="ark-job-board-attention text-secondary" data-attention-tone="watch" data-next-kind="watch">{{ $nextPresentation['label'] }}</span>
            @else
                <span class="text-secondary" data-next-kind="none">—</span>
            @endif
        </div>

        <div class="ark-job-board-card-band ark-job-board-card-band--footer d-flex align-items-center gap-2">
            <div class="min-w-0" data-job-board-row-activity data-job-card-activity>
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
            <span class="ms-auto small text-secondary text-nowrap" data-job-card-clock>{{ $row->updatedLabel }}</span>
        </div>
    </div>
</article>
