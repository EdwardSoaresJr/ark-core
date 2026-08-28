@props(['rail'])

@php
    $shop = $rail['shop'] ?? null;
@endphp

<aside class="ops-cal-capacity ops-cal-capacity--strip ops-board-shell">
    <div class="ops-cal-capacity__main">
        <div class="ops-cal-capacity__identity">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $rail['label'] }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-800">{{ $rail['focus_label'] }}</p>
        </div>

        @if ($shop)
            <div class="ops-cal-capacity__metrics">
                <div class="ops-cal-capacity__metric">
                    <span class="ops-cal-capacity__metric-label">Scheduled</span>
                    <span class="ops-cal-capacity__metric-value">{{ $shop['scheduled_label'] }}</span>
                </div>
                <div class="ops-cal-capacity__metric">
                    <span class="ops-cal-capacity__metric-label">Base</span>
                    <span class="ops-cal-capacity__metric-value ops-cal-capacity__metric-value--muted">
                        {{ $shop['available'] && $shop['base_label'] ? $shop['base_label'] : '—' }}
                    </span>
                </div>
                <div class="ops-cal-capacity__metric">
                    <span class="ops-cal-capacity__metric-label">Target</span>
                    <span class="ops-cal-capacity__metric-value ops-cal-capacity__metric-value--muted">
                        @if ($shop['available'] && $shop['target_label'])
                            {{ $shop['target_label'] }} · {{ $shop['target_percent'] }}%
                        @else
                            —
                        @endif
                    </span>
                </div>
                <p @class([
                    'ops-cal-capacity__status',
                    'ops-cal-capacity__status--beyond' => ($shop['status'] ?? '') === 'beyond_target',
                    'ops-cal-capacity__status--over' => ($shop['status'] ?? '') === 'overpacked',
                ])>
                    {{ $shop['status_copy'] }}
                </p>
            </div>
        @endif
    </div>

    @if (! empty($rail['warnings']))
        <div @class([
            'ops-cal-capacity__warnings',
            'ops-cal-capacity__warnings--beyond' => ($shop['status'] ?? '') === 'beyond_target',
            'ops-cal-capacity__warnings--amber' => ($shop['status'] ?? '') !== 'beyond_target',
        ])>
            @foreach ($rail['warnings'] as $warning)
                <p>{{ $warning }}</p>
            @endforeach
        </div>
    @elseif (! empty($rail['hint']))
        <p class="ops-cal-capacity__hint">{{ $rail['hint'] }}</p>
    @endif

    @if (! empty($rail['weekly']))
        <div class="ops-cal-capacity__week">
            @foreach ($rail['weekly'] as $day)
                <div class="ops-cal-capacity__week-day">
                    <div class="ops-cal-capacity__week-meta">
                        <span>{{ $day['day_label'] }}</span>
                        <span class="tabular-nums">
                            {{ number_format($day['assigned_hours'], 1) }}
                            @if (($day['capacity_hours'] ?? 0) > 0)
                                /{{ number_format($day['capacity_hours'], 1) }}h
                            @else
                                h
                            @endif
                        </span>
                    </div>
                    @if (($day['capacity_hours'] ?? 0) > 0)
                        <div class="ops-capacity-bar" aria-hidden="true">
                            <div
                                class="ops-capacity-bar__fill {{ $day['ratio'] >= 0.9 ? 'ops-capacity-bar__fill--warn' : '' }} {{ $day['ratio'] > 1 ? 'ops-capacity-bar__fill--over' : '' }}"
                                style="width: {{ min(100, (int) round($day['ratio'] * 100)) }}%"
                            ></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</aside>
