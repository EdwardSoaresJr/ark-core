@props([
    'card',
    'pxPerMinute' => 1.35,
])

@php
    $top = (int) round(($card['minutes_from_open'] ?? 0) * $pxPerMinute);
    $height = max(32, (int) round(($card['duration_minutes'] ?? 30) * $pxPerMinute));
    $hasComms = filled($card['call_url'] ?? null) || filled($card['text_url'] ?? null);
    $columnIndex = max(0, (int) ($card['column_index'] ?? 0));
    $columnCount = max(1, (int) ($card['column_count'] ?? 1));
    $widthPct = 100 / $columnCount;
    $leftPct = $columnIndex * $widthPct;
@endphp

<article
    class="ops-cal-card {{ $hasComms ? 'ops-cal-card--comms' : '' }}"
    style="top: {{ $top }}px; height: {{ $height }}px; left: calc({{ number_format($leftPct, 4, '.', '') }}% + 0.15rem); width: calc({{ number_format($widthPct, 4, '.', '') }}% - 0.3rem); right: auto;"
>
    <a
        href="{{ $card['show_url'] }}?edit=1"
        class="ops-cal-card__body"
    >
        <span class="ops-cal-card__customer">{{ $card['customer_name'] }}</span>
        @if ($card['vehicle_label'])
            <p class="ops-cal-card__vehicle">{{ $card['vehicle_label'] }}</p>
        @endif
        <p class="ops-cal-card__concern">{{ \Illuminate\Support\Str::limit($card['concern'], 48) }}</p>
        <p class="ops-cal-card__meta">
            <span>{{ $card['time_label'] }}–{{ $card['ends_label'] }}</span>
            @if ($card['estimated_labor_label'])
                <span>· {{ $card['estimated_labor_label'] }} scheduled</span>
            @endif
            @if ($card['arrival_type_label'])
                <span>· {{ $card['arrival_type_label'] }}</span>
            @endif
            <span>· {{ $card['status_label'] }}</span>
        </p>
    </a>
    @if ($hasComms)
        <div class="ops-cal-card__comms">
            @if (! empty($card['call_url']))
                <a href="{{ $card['call_url'] }}" class="ops-cal-card__comms-link">Call</a>
            @endif
            @if (! empty($card['text_url']))
                <a href="{{ $card['text_url'] }}" class="ops-cal-card__comms-link">Text</a>
            @endif
        </div>
    @endif
    {{-- Always available on hover — readable when columns are packed tight. --}}
    <div class="ops-cal-card__detail" role="tooltip">
        <p class="ops-cal-card__detail-name">{{ $card['customer_name'] }}</p>
        @if ($card['vehicle_label'])
            <p class="ops-cal-card__detail-line">{{ $card['vehicle_label'] }}</p>
        @endif
        <p class="ops-cal-card__detail-concern">{{ $card['concern'] }}</p>
        <p class="ops-cal-card__detail-line">
            {{ $card['time_label'] }}–{{ $card['ends_label'] }}
            @if ($card['estimated_labor_label'])
                · {{ $card['estimated_labor_label'] }} scheduled
            @endif
        </p>
        <p class="ops-cal-card__detail-line">
            {{ $card['status_label'] }}
            @if ($card['arrival_type_label'])
                · {{ $card['arrival_type_label'] }}
            @endif
        </p>
        <p class="ops-cal-card__detail-hint">Open to reschedule</p>
    </div>
</article>
