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
    $moneyCaptionLabel = match ($row->moneyCaption) {
        'due' => 'Balance due',
        'pending' => 'Pending',
        default => null,
    };
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
    $concernFull = implode("\n", $concernLines);
    $activityStrip = \App\Ark\Operations\Workboard\JobBoardActivityPresentation::forList($row->activityMarks);
    $nextPresentation = \App\Ark\Operations\Workboard\JobBoardCardNextPresentation::forSurface($surface);
    $nextAction = in_array($nextPresentation['kind'], ['action', 'warning', 'watch'], true)
        ? $nextPresentation['label']
        : null;
    $attentionTone = filled($surface->attention) ? $surface->attention : 'none';
@endphp

<tr
    id="ops-list-ro-{{ $repairOrder->repair_order_id }}"
    class="ark-job-board-list-row ark-job-board-list-row--{{ $attentionTone }}"
    data-job-board-row
    data-job-card
    data-ro-id="{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $row->searchHaystack }}"
    data-home-tech-id="{{ $row->technicianId ?? '' }}"
    data-home-lane-key="{{ $row->laneKey }}"
    data-workboard-attention="{{ $surface->attention }}"
    @if ($surface->waitingOnCustomerDecision) data-workboard-decision="1" @endif
>
    <td class="ark-job-board-col-ro">
        <a
            href="{{ $builderUrl }}"
            class="ark-job-board-ro-link"
            data-job-board-row-ro
            data-job-card-ro
        >RO #{{ $repairOrder->repair_order_id }}</a>
    </td>
    <td class="ark-job-board-col-customer-vehicle" data-job-board-row-customer-vehicle>
        <div class="ark-job-board-visit">
            <div class="ark-job-board-visit__vehicle text-truncate">
                <a href="{{ $builderUrl }}" class="ark-job-board-visit__vehicle-link" data-job-board-row-vehicle data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
            </div>
            <div class="ark-job-board-visit__customer text-truncate">
                @if ($surface->customerHubUrl)
                    <a href="{{ $surface->customerHubUrl }}" class="ark-job-board-visit__customer-link" data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
                @else
                    <span data-job-board-row-customer data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
                @endif
            </div>
            @if ($nextAction)
                <div
                    class="ark-job-board-visit__next ark-job-board-visit__next--{{ $nextPresentation['kind'] }} text-truncate"
                    data-next-kind="{{ $nextPresentation['kind'] }}"
                    title="{{ $nextAction }}"
                >{{ $nextAction }}</div>
            @endif
        </div>
    </td>
    <td
        class="text-body ark-job-board-col-concern"
        data-job-board-row-concern
        title="{{ $concernFull !== '' ? $concernFull : null }}"
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
    </td>
    <td class="ark-job-board-col-status">
        <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-board-row-status data-job-card-status>
            <x-tabler.status-badge :tone="$statusTone">{{ $statusLabel }}</x-tabler.status-badge>
        </a>
    </td>
    <td class="text-nowrap ark-job-board-col-tech" data-job-board-row-technician>
        <span class="ark-job-board-tech">{{ $row->technicianName ?? '—' }}</span>
    </td>
    <td class="text-end ark-job-board-col-total" data-job-board-row-total data-job-card-total>
        <div @class(['ark-job-board-money', 'ark-job-board-money--due' => $isBalanceDue])>
            <span class="ark-job-board-money__amount ark-money">{{ $moneyLabel }}</span>
            @if ($moneyCaptionLabel)
                <span class="ark-job-board-money__caption">{{ $moneyCaptionLabel }}</span>
            @endif
        </div>
    </td>
    <td class="ark-job-board-col-attention" data-job-board-row-attention data-attention="{{ $surface->attention }}">
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
    <td class="text-nowrap ark-job-board-col-activity" data-job-board-row-activity data-job-card-activity>
        @if ($activityStrip['visible'] === [])
            <span class="text-secondary" data-job-board-activity-empty>—</span>
        @else
            <div class="d-inline-flex align-items-center flex-nowrap ark-job-board-activity" role="group" aria-label="Activity">
                @foreach ($activityStrip['visible'] as $mark)
                    @php
                        $markHref = \App\Ark\Operations\Workboard\JobBoardActivityPresentation::destinationFor($mark->key, $card->href);
                    @endphp
                    @if ($markHref)
                        {{-- Keep Bootstrap tooltip off the <a>; data-bs-toggle on anchors can swallow hash navigation. --}}
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
    </td>
    <td class="ark-job-board-col-updated" data-job-board-row-updated data-job-card-clock>
        <span class="ark-job-board-updated">{{ $row->updatedLabel }}</span>
    </td>
    <td class="ark-job-board-col-open">
        <a href="{{ $builderUrl }}" class="btn btn-sm btn-ghost-secondary ark-job-board-open" aria-label="Open RO #{{ $repairOrder->repair_order_id }}">
            <x-tabler.icon name="chevron-right" />
        </a>
    </td>
</tr>
