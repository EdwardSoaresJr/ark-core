@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageCard $card */
    /** @var array<int, \App\Ark\Operations\Today\AdvisorHomeCardSurface> $homeCardSurfaces */
    $repairOrder = $card->repairOrder;
    $surface = $homeCardSurfaces[$repairOrder->id] ?? null;
    $moneyLabel = filled($surface?->moneyLabel) ? $surface->moneyLabel : '$0';
    $moneyCaption = $surface?->moneyCaption;
    $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
    $isRecommended = isset($recommendedRepairOrderId) && $recommendedRepairOrderId === $repairOrder->repair_order_id;
    $chip = $surface?->chip;
    $statusLabel = filled($chip?->label) ? $chip->label : 'No Status';
    $clockDisplay = $surface?->clockLabel;
    $attention = $surface?->attention ?? 'normal';
    $isBalanceDue = $moneyCaption === 'due';
    $activityMarks = $surface?->activityMarks ?: \App\Ark\Operations\Workboard\WorkboardCardActivityProjection::idle();
    $exceptionLabel = $surface?->exceptionLabel;
    $exceptionExtraCount = $surface?->exceptionExtraCount ?? 0;
    $exceptionTone = $surface?->exceptionTone ?? 'none';
    $exceptionItems = $surface?->exceptionItems ?? [];
    $exceptionExtras = array_slice($exceptionItems, 1);
    $exceptionMoreHint = $exceptionExtras === []
        ? ''
        : 'Also: '.implode(', ', array_column($exceptionExtras, 'label'));
    $builderUrl = $card->href.'#builder';
    $homeSearch = strtolower(collect([
        $repairOrder->repair_order_id,
        $customerName,
        $card->vehicleLabel,
        $chip?->label,
        $statusLabel,
        $clockDisplay,
        $moneyLabel,
        $exceptionLabel,
        ...array_column($exceptionExtras, 'label'),
    ])->filter()->join(' '));
    $attentionColor = match ($attention) {
        'watch', 'attention' => 'yellow',
        'critical' => 'red',
        default => null,
    };
    $toneText = fn (?string $tone): ?string => match ($tone) {
        'attention' => 'text-warning',
        'critical', 'missed' => 'text-danger',
        default => null,
    };
    $markClasses = fn (string $state): string => match ($state) {
        'ready' => 'bg-secondary-lt',
        'sent' => 'bg-azure-lt',
        'engaged' => 'bg-azure text-white',
        'attention' => 'bg-orange text-white',
        default => 'bg-transparent text-secondary',
    };
@endphp

<div
    id="ops-card-ro-{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $homeSearch }}"
    data-home-tech-id="{{ $repairOrder->concerns->flatMap->workGroups->first(fn ($g) => $g->hasOwner())?->owner_user_id ?? '' }}"
    x-show="cardMatches($el)"
    x-cloak
>
    <article
        @class(['card card-sm', 'border-primary' => $isRecommended])
        data-job-card
        data-workboard-attention="{{ $attention }}"
        @if ($isRecommended) data-workboard-recommended @endif
        @if ($surface?->waitingOnCustomerDecision) data-workboard-decision="1" @endif
    >
        @if ($attentionColor)
            <div class="card-status-start bg-{{ $attentionColor }}"></div>
        @endif
        <div class="card-body p-2" data-job-card-scan>
            <div class="d-flex align-items-baseline gap-2">
                <a href="{{ $builderUrl }}" class="fw-bold text-reset" data-job-card-ro>RO #{{ $repairOrder->repair_order_id }}</a>
                <span @class(['ms-auto fw-bold ark-money text-nowrap', 'text-danger' => $isBalanceDue]) data-job-card-total>
                    {{ $moneyLabel }}
                    @if ($isBalanceDue)
                        <span class="small fw-normal">due</span>
                    @elseif (filled($moneyCaption))
                        <span class="small fw-normal text-secondary">{{ $moneyCaption }}</span>
                    @endif
                </span>
            </div>
            {{-- The row renders even when empty, as on the legacy card, so lanes keep an even rhythm. --}}
            <div
                @class(['small fw-medium d-flex align-items-center gap-1', $toneText($exceptionTone)])
                data-job-card-exception="{{ filled($exceptionLabel) ? $exceptionTone : 'empty' }}"
                @unless (filled($exceptionLabel)) aria-hidden="true" @endunless
            >
                @if (filled($exceptionLabel))
                    <span class="text-truncate">{{ $exceptionLabel }}</span>
                    @if ($exceptionExtraCount > 0)
                        <span class="dropdown">
                            <button
                                type="button"
                                class="btn btn-sm btn-ghost-secondary px-1 py-0"
                                data-bs-toggle="dropdown"
                                data-bs-popper-config='{"strategy":"fixed"}'
                                aria-expanded="false"
                                title="{{ $exceptionMoreHint }}"
                                aria-label="{{ $exceptionMoreHint }}"
                            >+{{ $exceptionExtraCount }}</button>
                            <span class="dropdown-menu" role="list" data-job-card-exception-list>
                                @foreach ($exceptionItems as $item)
                                    <span @class(['dropdown-item-text small', $toneText($item['tone'] ?? null)]) role="listitem" data-job-card-exception-item>{{ $item['label'] }}</span>
                                @endforeach
                            </span>
                        </span>
                    @endif
                @endif
            </div>
            <a href="{{ $builderUrl }}" class="d-block fw-bold text-reset text-truncate mt-1" data-job-card-vehicle>{{ $card->vehicleLabel }}</a>
            <div class="small text-truncate">
                @if ($surface?->customerHubUrl)
                    <a href="{{ $surface->customerHubUrl }}" class="text-secondary" data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
                @else
                    <span class="text-secondary" data-job-card-customer>{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <a href="{{ $builderUrl }}" class="text-decoration-none" data-job-card-status data-job-card-status-tone="{{ $chip?->tone ?? 'none' }}">
                    <x-tabler.status-badge :tone="filled($chip?->statusColor) ? \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($chip->statusColor) : ($chip?->tone ?? 'move')" data-job-card-status-label="label">{{ $statusLabel }}</x-tabler.status-badge>
                </a>
                <span @class(['ms-auto small', 'text-secondary' => ! in_array($attention, ['attention', 'critical'], true), 'text-warning' => $attention === 'attention', 'text-danger' => $attention === 'critical']) data-job-card-clock>{{ $clockDisplay }}</span>
            </div>
        </div>
        <div @class(['card-footer p-1', 'bg-yellow-lt' => $attention === 'attention', 'bg-red-lt' => $attention === 'critical']) data-job-card-activity>
            <div class="d-flex justify-content-between" role="group" aria-label="Activity">
                @foreach ($activityMarks as $mark)
                    {{-- tabindex -1: a tap focuses the mark, which shows its tooltip, without adding six tab stops per card. --}}
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
        </div>
    </article>
</div>
