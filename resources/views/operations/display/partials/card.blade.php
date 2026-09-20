@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeAttentionRow $row */
@endphp

<article class="ops-shop-display-card">
    <p class="ops-shop-display-card__customer">{{ $row->customerName }}</p>
    <p class="ops-shop-display-card__vehicle">{{ $row->vehicleLabel }}</p>

    <div class="ops-shop-display-card__priority">
        <span @class([
            'ops-shop-display-card__status',
            'ops-shop-display-card__status--' . $row->statusChipTone,
        ])>{{ $row->statusBadge }}</span>

        @if ($row->attentionReason)
            <span class="ops-shop-display-card__reason">{{ $row->attentionReason }}</span>
        @endif

        @if ($row->totalLabel)
            <span class="ops-shop-display-card__money">{{ $row->totalLabel }}</span>
        @endif
    </div>

    <div class="ops-shop-display-card__age-row">
        @if ($row->staleLevel !== null)
            <span @class([
                'ops-shop-display-card__stale',
                'ops-shop-display-card__stale--' . $row->staleLevel,
            ])>Stale</span>
        @endif
        <span @class([
            'ops-shop-display-card__age',
            'ops-shop-display-card__age--' . ($row->staleLevel ?? 'normal'),
        ])>{{ $row->ageLabel }}</span>
    </div>
</article>
