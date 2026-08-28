@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageCard $card */
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\RepairOrders\EstimateTotals> $repairOrderTotals */
    /** @var array<int, \App\Ark\Operations\Today\AdvisorHomeCardSurface> $homeCardSurfaces */
    $repairOrder = $card->repairOrder;
    $surface = $homeCardSurfaces[$repairOrder->id] ?? null;
    $totals = $repairOrderTotals[$repairOrder->id] ?? null;
    $totalCents = $totals instanceof \App\Ark\Operations\RepairOrders\EstimateTotals
        ? $totals->totalCents()
        : 0;
    $totalLabel = $totalCents > 0 ? $totals->format($totalCents) : '$0.00';
    $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
    $isRecommended = isset($recommendedRepairOrderId) && $recommendedRepairOrderId === $repairOrder->repair_order_id;
    $chip = $surface?->chip;
    $laborProgress = $surface?->laborProgress;
    $statusMoves = $surface?->statusMoves ?? [];
    $homeSearch = strtolower(collect([
        $repairOrder->repair_order_id,
        $customerName,
        $card->vehicleLabel,
        $surface?->customerPhone,
        $chip?->label,
        $surface?->concernLabel,
        $surface?->nextMoveLabel,
        $surface?->scheduleLabel,
    ])->filter()->join(' '));
    $builderUrl = $card->href.'#builder';
    $createdAgeLabel = 'RO created '.$repairOrder->created_at->diffForHumans(short: true, parts: 1);
    $promiseLabel = $surface?->promiseLabel;
    $isBalanceDue = $chip?->label === 'Balance Due';
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
        ])
        @if ($card->countsAsNeedsAttention) data-workboard-attention="needs" @endif
    >
        <div class="ops-job-card__head">
            <div class="ops-job-card__identity">
                <a href="{{ $builderUrl }}" class="ops-job-card__ro">RO #{{ $repairOrder->repair_order_id }}</a>
                @if ($surface?->techInitials)
                    <span class="ops-job-card__avatar" title="Repair Action owner">{{ $surface->techInitials }}</span>
                @endif
            </div>

            @if ($chip !== null)
                <x-operations.lifecycle-status-menu
                    :repair-order="$repairOrder"
                    :label="$chip->label"
                    :tone="$chip->tone"
                    :status-moves="$statusMoves"
                    :confirm-base-url="$card->href"
                />
            @else
                <span class="ops-job-card__chip ops-job-card__chip--neutral">Open</span>
            @endif
        </div>

        <div class="ops-job-card__body">
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
                @if ($surface?->customerPhone)
                    @if ($surface->textCustomerUrl)
                        <a
                            href="{{ $surface->textCustomerUrl }}"
                            class="ops-job-card__phone"
                            title="Open communications"
                        >{{ $surface->customerPhone }}</a>
                    @else
                        <span class="ops-job-card__phone">{{ $surface->customerPhone }}</span>
                    @endif
                @endif
            </p>
            <a href="{{ $builderUrl }}" class="ops-job-card__vehicle">
                <svg class="ops-job-card__vehicle-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4.75 6.5 6 4.06A2 2 0 0 1 7.77 3h4.46A2 2 0 0 1 14 4.06L15.25 6.5H16a1 1 0 0 1 1 1v.5a1 1 0 0 1-1 1h-.09c.06.24.09.49.09.75v4.5A1.75 1.75 0 0 1 14.25 16h-.5A1.75 1.75 0 0 1 12 14.25V14H8v.25A1.75 1.75 0 0 1 6.25 16h-.5A1.75 1.75 0 0 1 4 14.25v-4.5c0-.26.03-.51.09-.75H4a1 1 0 0 1-1-1V7.5a1 1 0 0 1 1-1h.75Zm1.9-1.55L5.6 7h8.8l-1.05-2.05a.5.5 0 0 0-.44-.28H7.09a.5.5 0 0 0-.44.28ZM6.5 11.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                </svg>
                {{ $card->vehicleLabel }}
            </a>
            @if (filled($surface?->scheduleLabel))
                <p @class([
                    'ops-job-card__schedule',
                    'ops-job-card__schedule--' . ($surface->scheduleTone ?? 'none'),
                ])>{{ $surface->scheduleLabel }}</p>
            @endif
            @if (filled($surface?->concernLabel))
                <p class="ops-job-card__concern">{{ $surface->concernLabel }}</p>
            @endif
            @if (filled($surface?->nextMoveLabel))
                <p class="ops-job-card__next">{{ $surface->nextMoveLabel }}</p>
            @endif
        </div>

        <div class="ops-job-card__meta">
            @if ($isBalanceDue)
                <span class="ops-job-card__balance">
                    <span class="ops-job-card__balance-amount">{{ $totalLabel }}</span>
                    <span class="ops-job-card__balance-label">Balance Due</span>
                </span>
            @else
                <span class="ops-job-card__total">{{ $totalLabel }}</span>

                @if (filled($promiseLabel))
                    <span @class([
                        'ops-job-card__promise',
                        'ops-job-card__promise--' . ($surface?->promiseTone ?? 'neutral'),
                    ])>
                        <svg class="ops-job-card__promise-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c0-.414.336-.75.75-.75h10.5a.75.75 0 0 1 0 1.5H5.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                        </svg>
                        {{ $promiseLabel }}
                    </span>
                @endif
            @endif
        </div>

        @if ($laborProgress !== null)
            <div class="ops-job-card__progress" aria-label="{{ $laborProgress->label }}">
                <div class="ops-job-card__progress-head">
                    <span class="ops-job-card__progress-label">{{ $laborProgress->label }}</span>
                    <span class="ops-job-card__progress-percent">{{ $laborProgress->percent }}%</span>
                </div>
                <div class="ops-job-card__progress-track">
                    <span class="ops-job-card__progress-fill" style="width: {{ $laborProgress->percent }}%"></span>
                </div>
            </div>
        @endif

        <div class="ops-job-card__foot">
            <span class="ops-job-card__age">{{ $createdAgeLabel }}</span>

            @if ($surface?->estimateEventLabel)
                <span @class([
                    'ops-job-card__event',
                    'ops-job-card__event--' . ($surface->estimateEventKind ?? 'sent'),
                ])>
                    @if ($surface->estimateEventKind === 'viewed')
                        <svg class="ops-job-card__event-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                            <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg class="ops-job-card__event-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
                        </svg>
                    @endif
                    {{ $surface->estimateEventLabel }}
                </span>
            @endif
        </div>
    </article>
</div>
