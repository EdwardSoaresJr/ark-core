@props(['rail'])

@php
    $shop = $rail['shop'] ?? null;
    $shopStatus = $shop['status'] ?? '';
@endphp

<div class="card card-sm" data-schedule-capacity>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-end column-gap-4 row-gap-2">
            <div>
                <div class="subheader">{{ $rail['label'] }}</div>
                <div class="fw-medium">{{ $rail['focus_label'] }}</div>
            </div>

            @if ($shop)
                <div>
                    <div class="subheader">Scheduled</div>
                    <div class="fw-bold ark-money">{{ $shop['scheduled_label'] }}</div>
                </div>
                <div>
                    <div class="subheader">Base</div>
                    <div class="text-secondary ark-money">{{ $shop['available'] && $shop['base_label'] ? $shop['base_label'] : '-' }}</div>
                </div>
                <div>
                    <div class="subheader">Target</div>
                    <div class="text-secondary ark-money">
                        @if ($shop['available'] && $shop['target_label'])
                            {{ $shop['target_label'] }} · {{ $shop['target_percent'] }}%
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div @class([
                    'small flex-fill',
                    'text-danger' => $shopStatus === 'beyond_target',
                    'text-warning' => $shopStatus === 'overpacked',
                    'text-secondary' => ! in_array($shopStatus, ['beyond_target', 'overpacked'], true),
                ])>{{ $shop['status_copy'] }}</div>
            @endif
        </div>

        @if (! empty($rail['warnings']))
            <div @class([
                'alert mt-3 mb-0 py-2 small',
                'alert-danger' => $shopStatus === 'beyond_target',
                'alert-warning' => $shopStatus !== 'beyond_target',
            ]) role="status">
                @foreach ($rail['warnings'] as $warning)
                    <div>{{ $warning }}</div>
                @endforeach
            </div>
        @elseif (! empty($rail['hint']))
            <div class="small text-secondary mt-2">{{ $rail['hint'] }}</div>
        @endif

        @if (! empty($rail['weekly']))
            <div class="ark-cal-capacity-week mt-2">
                @foreach ($rail['weekly'] as $day)
                    <div>
                        <div class="d-flex justify-content-between gap-1 small">
                            <span class="text-secondary">{{ $day['day_label'] }}</span>
                            <span class="ark-money">
                                {{ number_format($day['assigned_hours'], 1) }}@if (($day['capacity_hours'] ?? 0) > 0)/{{ number_format($day['capacity_hours'], 1) }}h @else h @endif
                            </span>
                        </div>
                        @if (($day['capacity_hours'] ?? 0) > 0)
                            <div class="progress progress-sm mt-1" aria-hidden="true">
                                <div
                                    @class([
                                        'progress-bar',
                                        'bg-danger' => $day['ratio'] > 1,
                                        'bg-warning' => $day['ratio'] >= 0.9 && $day['ratio'] <= 1,
                                    ])
                                    style="width: {{ min(100, (int) round($day['ratio'] * 100)) }}%"
                                ></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
