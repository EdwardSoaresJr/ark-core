@php
    /** @var array<string, mixed> $panel */
    $barSegments = 8;
    $posture = $panel['posture'] ?? '';
@endphp

<div class="card-body p-2 p-md-3 border-bottom" data-market-pressure-panel>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="flex-fill" style="min-width: 0">
            <div @class(['fw-bold', 'text-warning' => $posture === 'pressure'])>{{ $panel['headline'] }}</div>
            @if ($panel['eligible_closes'] > 0)
                <dl class="d-flex flex-wrap gap-3 small mt-2 mb-0">
                    <div>
                        <dt class="d-inline text-secondary">Target:</dt>
                        <dd class="d-inline fw-medium mb-0">{{ $panel['target'] }}</dd>
                    </div>
                    @if ($panel['missed_count'] > 0)
                        <div>
                            <dt class="d-inline text-warning">Missed:</dt>
                            <dd class="d-inline fw-medium text-warning mb-0">{{ $panel['missed_count'] }} opportunities</dd>
                        </div>
                    @endif
                </dl>
            @endif
            @if (filled($panel['why_care'] ?? null))
                <p class="small text-secondary mt-2 mb-0">{{ $panel['why_care'] }}</p>
            @endif
        </div>
        <span @class([
            'badge text-uppercase',
            'bg-yellow-lt' => $posture === 'pressure',
            'bg-green-lt' => $posture === 'healthy',
            'bg-secondary-lt' => ! in_array($posture, ['pressure', 'healthy'], true),
        ])>{{ $panel['posture'] ?? 'observe' }}</span>
    </div>

    @if (! empty($panel['advisor_breakdown']))
        <div class="border-top mt-3 pt-3">
            <div class="subheader">Advisor breakdown</div>
            <ul class="list-unstyled mt-2 mb-0">
                @foreach ($panel['advisor_breakdown'] as $advisor)
                    @php
                        $filled = $advisor['paid_closes'] > 0
                            ? (int) round(($advisor['review_requests'] / $advisor['paid_closes']) * $barSegments)
                            : 0;
                    @endphp
                    <li class="small mb-2">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="fw-medium">{{ $advisor['name'] }}</span>
                            <span class="fw-bold">{{ $advisor['review_requests'] }}/{{ $advisor['paid_closes'] }}</span>
                        </div>
                        <div class="d-flex gap-1 mt-1" aria-hidden="true">
                            @foreach (range(1, $barSegments) as $segment)
                                <span @class([
                                    'flex-fill rounded',
                                    'bg-primary' => $segment <= $filled,
                                    'bg-secondary-lt' => $segment > $filled,
                                ]) style="height: 0.5rem"></span>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="small mt-3 mb-0">
        <a href="{{ $panel['detail_url'] }}">Full market pressure →</a>
    </p>
</div>
