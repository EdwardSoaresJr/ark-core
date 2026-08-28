@if ($cockpit->hotCards !== [])
    <nav class="ops-advisor-home-hot" aria-label="Look at first">
        <span class="ops-advisor-home-hot__label">Look at first</span>
        <div class="ops-advisor-home-hot__track">
            @foreach ($cockpit->hotCards as $hot)
                <a
                    href="#ops-card-ro-{{ $hot->repairOrderId }}"
                    @class([
                        'ops-advisor-home-hot__chip',
                        'ops-advisor-home-hot__chip--' . $hot->urgencyTier,
                        'ops-advisor-home-hot__chip--recommended' => $hot->isRecommended,
                    ])
                >
                    <span class="ops-advisor-home-hot__vehicle">{{ $hot->vehicleLabel }}</span>
                    @if ($hot->totalCents > 0)
                        <span class="ops-advisor-home-hot__money">{{ $hot->totalLabel }}</span>
                    @endif
                    <span class="ops-advisor-home-hot__action">{{ $hot->actionStatement }}</span>
                    <span class="ops-advisor-home-hot__lane">{{ $hot->columnLabel }}</span>
                </a>
            @endforeach
        </div>
    </nav>
@endif
