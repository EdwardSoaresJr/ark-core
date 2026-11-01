@php
    $slotHeight = (int) round($w['slot_minutes'] * $pxPerMinute);
@endphp

<div
    class="card"
    x-data="arkScheduleAppointmentPopover"
    :style="{ '--ark-cal-pop-top': popTop + 'px', '--ark-cal-pop-left': popLeft + 'px' }"
    @click="close()"
    @click.outside="close()"
    @keydown.escape.window="close()"
    @scroll.window="reposition()"
    @scroll.capture="reposition()"
    @resize.window="reposition()"
    data-schedule-board="day"
>
    @if (($w['total_count'] ?? 0) === 0)
        <div class="card-body border-bottom py-2">
            <div class="fw-medium">No appointments yet.</div>
            <div class="small text-secondary">Click a time slot below, or use Schedule to book the next drop-off.</div>
        </div>
    @endif
    <div class="ark-cal-day" style="--ark-cal-grid-height: {{ $gridHeight }}px;">
        <div class="ark-cal-day__times" aria-hidden="true">
            <div class="ark-cal-day__head"></div>
            @foreach ($w['slots'] as $slot)
                <div class="ark-cal-day__tick" style="height: {{ $slotHeight }}px;">{{ $slot['label'] }}</div>
            @endforeach
        </div>

        @foreach ($w['lane_rows'] as $lane)
            <div class="ark-cal-day__lane" style="--ark-cal-lane-columns: {{ max(1, (int) collect($lane['cards'])->max('column_count')) }};" data-schedule-lane>
                <div class="ark-cal-day__head subheader">{{ $lane['label'] }}</div>
                <div class="ark-cal-day__body">
                    @foreach ($w['slots'] as $slot)
                        <a
                            href="{{ route('operations.schedule', [
                                'starts_at' => $slot['starts_at'],
                                'ends_at' => $slot['ends_at'],
                            ]) }}"
                            class="ark-cal-slot"
                            style="height: {{ $slotHeight }}px;"
                            title="Schedule {{ $slot['label'] }}"
                            data-schedule-slot
                        ><span class="visually-hidden">Schedule {{ $slot['label'] }}</span></a>
                    @endforeach

                    @foreach ($lane['cards'] as $card)
                        @include('operations.appointments.partials.calendar-card', [
                            'card' => $card,
                            'pxPerMinute' => $pxPerMinute,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
