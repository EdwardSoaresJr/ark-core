@props([
    'card',
    'pxPerMinute' => 1.35,
])

@php
    $appointmentId = (int) ($card['id'] ?? 0);
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
    :class="{ 'ops-cal-appt-event--open': openAppointmentId === {{ $appointmentId }} }"
>
    <button
        type="button"
        class="ops-cal-card__body"
        @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
        :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
    >
        @if (filled($card['repair_order_number'] ?? null))
            <span class="ops-cal-card__ro">#{{ $card['repair_order_number'] }}</span>
        @endif
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
    </button>
    @if ($hasComms)
        <div class="ops-cal-card__comms" @click.stop>
            @if (! empty($card['call_url']))
                <a href="{{ $card['call_url'] }}" class="ops-cal-card__comms-link">Call</a>
            @endif
            @if (! empty($card['text_url']))
                <a href="{{ $card['text_url'] }}" class="ops-cal-card__comms-link">Text</a>
            @endif
        </div>
    @endif

    @include('operations.appointments.partials.appointment-detail-popover', [
        'card' => $card,
    ])
</article>
