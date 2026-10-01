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
@endphp

<div
    class="ops-ro-card-wrap ops-job-card-wrap"
    id="ops-card-ro-{{ $repairOrder->repair_order_id }}"
    data-home-search="{{ $homeSearch }}"
    data-home-tech-id="{{ $repairOrder->concerns->flatMap->workGroups->first(fn ($g) => $g->hasOwner())?->owner_user_id ?? '' }}"
    x-show="cardMatches($el)"
    x-cloak
>
    <article
        @class([
            'ops-job-card',
            'ops-ro-card--home',
            'ops-job-card--recommended' => $isRecommended,
            'ops-job-card--'.$attention => $attention !== 'normal',
        ])
        data-workboard-attention="{{ $attention }}"
        @if ($surface?->waitingOnCustomerDecision) data-workboard-decision="1" @endif
    >
        <div class="ops-job-card__scan">
            <div class="ops-job-card__head">
                <a href="{{ $builderUrl }}" class="ops-job-card__ro">RO #{{ $repairOrder->repair_order_id }}</a>
                <span @class([
                    'ops-job-card__total',
                    'ops-job-card__total--due' => $isBalanceDue,
                ])>
                    {{ $moneyLabel }}
                    @if ($isBalanceDue)
                        <span class="ops-job-card__money-caption">due</span>
                    @elseif (filled($moneyCaption))
                        <span class="ops-job-card__money-caption">{{ $moneyCaption }}</span>
                    @endif
                </span>
            </div>
            <p
                @class([
                    'ops-job-card__exception',
                    'ops-job-card__exception--'.$exceptionTone => $exceptionTone !== 'none',
                    'ops-job-card__exception--empty' => ! filled($exceptionLabel),
                ])
                @if (! filled($exceptionLabel)) aria-hidden="true" @endif
            >
                @if (filled($exceptionLabel))
                    <span class="ops-job-card__exception-label">{{ $exceptionLabel }}</span>
                    @if ($exceptionExtraCount > 0)
                        <span
                            class="ops-job-card__exception-more"
                            x-data="{ open: false }"
                            @click.outside="open = false"
                        >
                            <button
                                type="button"
                                class="ops-job-card__exception-more-btn"
                                :aria-expanded="open.toString()"
                                aria-haspopup="true"
                                title="{{ $exceptionMoreHint }}"
                                aria-label="{{ $exceptionMoreHint }}"
                                @click.stop="open = !open"
                            >+{{ $exceptionExtraCount }}</button>
                            <span
                                class="ops-job-card__exception-list"
                                x-show="open"
                                x-cloak
                                role="list"
                            >
                                @foreach ($exceptionItems as $item)
                                    <span
                                        @class([
                                            'ops-job-card__exception-list-item',
                                            'ops-job-card__exception-list-item--'.$item['tone'] => ($item['tone'] ?? 'none') !== 'none',
                                        ])
                                        role="listitem"
                                    >{{ $item['label'] }}</span>
                                @endforeach
                            </span>
                        </span>
                    @endif
                @endif
            </p>
            <a href="{{ $builderUrl }}" class="ops-job-card__vehicle">
                {{ $card->vehicleLabel }}
            </a>
            <p class="ops-job-card__customer">
                @if ($surface?->customerHubUrl)
                    <a href="{{ $surface->customerHubUrl }}" class="ops-job-card__customer-link">
                        {{ $customerName !== '' ? $customerName : 'Unknown customer' }}
                    </a>
                @else
                    <span class="ops-job-card__customer-link ops-job-card__customer-link--static">
                        {{ $customerName !== '' ? $customerName : 'Unknown customer' }}
                    </span>
                @endif
            </p>
        </div>

        <div class="ops-job-card__status-row">
            <a
                href="{{ $builderUrl }}"
                @class([
                    'ops-job-card__status',
                    'ops-job-card__chip',
                    'ops-job-card__chip--quiet',
                    'ops-job-card__chip--status-'.($chip?->statusColor) => filled($chip?->statusColor),
                ])
            >
                <span class="ops-job-card__chip-label">{{ $statusLabel }}</span>
            </a>
            <span class="ops-job-card__clock">{{ $clockDisplay }}</span>
        </div>

        <div class="ops-job-card__activity" role="group" aria-label="Activity">
            @foreach ($activityMarks as $mark)
                <span
                    @class([
                        'ops-job-card__mark',
                        'ops-job-card__mark--'.$mark->state->value,
                    ])
                    aria-label="{{ $mark->tooltip }}"
                >
                    @include('operations.workboard.partials.activity-mark-icon', [
                        'key' => $mark->key,
                        'filled' => $mark->active,
                        'badge' => $mark->badge,
                    ])
                    <span class="ops-job-card__tip" role="tooltip">{{ $mark->tooltip }}</span>
                </span>
            @endforeach
        </div>
    </article>
</div>
