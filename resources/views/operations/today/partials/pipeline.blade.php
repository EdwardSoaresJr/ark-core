@php
    /** @var list<\App\Ark\Operations\Today\TodayPipelineMetric> $pipeline */
@endphp

<div class="ops-today__overview-col" aria-labelledby="ops-today-pipeline">
    <div class="ops-today__overview-head">
        <h2 id="ops-today-pipeline" class="ops-today__overview-title">Pipeline</h2>
        <p class="ops-today__overview-copy">Operational cash flow — not accounting. Every row links to the repair orders behind it.</p>
    </div>

    <ul class="ops-today-metric-list">
        @foreach ($pipeline as $metric)
            <li>
                <a
                    href="{{ $metric->inventoryUrl }}"
                    @class([
                        'ops-today-metric-row',
                        'ops-today-metric-row--emphasis' => $metric->emphasized,
                    ])
                >
                    <span class="ops-today-metric-row__label">{{ $metric->label }}</span>
                    <span class="ops-today-metric-row__value">{{ $metric->amountLabel }}</span>
                    <span class="ops-today-metric-row__meta">
                        {{ $metric->repairOrderCount === 1 ? '1 RO' : $metric->repairOrderCount.' ROs' }}
                        · {{ $metric->hint }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
