@php
    /** @var list<\App\Ark\Operations\Today\TodayWorkQueueRow> $workQueues */
@endphp

<div class="ops-today__overview-col" aria-labelledby="ops-today-work-queues">
    <div class="ops-today__overview-head">
        <h2 id="ops-today-work-queues" class="ops-today__overview-title">Work queues</h2>
        <p class="ops-today__overview-copy">Count, dollars waiting, and oldest age — open Operations for the full list.</p>
    </div>

    <ul class="ops-today-metric-list">
        @foreach ($workQueues as $queue)
            <li>
                <a href="{{ $queue->workboardUrl }}" class="ops-today-metric-row">
                    <span class="ops-today-metric-row__label">{{ $queue->label }}</span>
                    <span class="ops-today-metric-row__value">{{ $queue->revenueTrappedLabel }}</span>
                    <span class="ops-today-metric-row__meta">
                        {{ $queue->count }} · oldest {{ $queue->oldestAgeLabel ?? '—' }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
