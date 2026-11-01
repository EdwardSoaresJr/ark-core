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
    class="ark-cal-card"
    style="top: {{ $top }}px; height: {{ $height }}px; left: calc({{ number_format($leftPct, 4, '.', '') }}% + 0.15rem); width: calc({{ number_format($widthPct, 4, '.', '') }}% - 0.3rem);"
    :class="{ 'is-open': openAppointmentId === {{ $appointmentId }} }"
    data-schedule-card
>
    <button
        type="button"
        class="ark-cal-card__body"
        @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
        :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
    >
        <span class="d-block text-truncate">
            @if (filled($card['repair_order_number'] ?? null))
                <span class="text-secondary">#{{ $card['repair_order_number'] }}</span>
            @endif
            <span class="fw-bold">{{ $card['customer_name'] }}</span>
        </span>
        @if ($card['vehicle_label'])
            <span class="d-block text-truncate">{{ $card['vehicle_label'] }}</span>
        @endif
        <span class="d-block text-truncate text-secondary">{{ \Illuminate\Support\Str::limit($card['concern'], 48) }}</span>
        <span class="d-block text-truncate text-secondary">
            {{ $card['time_label'] }}–{{ $card['ends_label'] }}
            @if ($card['estimated_labor_label'])
                · {{ $card['estimated_labor_label'] }} scheduled
            @endif
            @if ($card['arrival_type_label'])
                · {{ $card['arrival_type_label'] }}
            @endif
            · {{ $card['status_label'] }}
        </span>
    </button>
    @if ($hasComms)
        <div class="ark-cal-card__comms btn-list" @click.stop data-schedule-card-comms>
            @if (! empty($card['call_url']))
                <a href="{{ $card['call_url'] }}" class="btn btn-sm px-2 py-0">Call</a>
            @endif
            @if (! empty($card['text_url']))
                <a href="{{ $card['text_url'] }}" class="btn btn-sm px-2 py-0">Text</a>
            @endif
        </div>
    @endif

    @include('operations.appointments.partials.appointment-detail-popover', [
        'card' => $card,
    ])
</article>
