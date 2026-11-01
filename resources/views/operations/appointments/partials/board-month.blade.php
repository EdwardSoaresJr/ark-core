@php
    $queryBase = $queryBase ?? [];
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
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
    data-schedule-board="month"
>
    <div class="ark-cal-month" role="grid" aria-label="Month schedule">
        <div class="ark-cal-month__head" role="row">
            @foreach ($weekdays as $weekday)
                <div class="subheader" role="columnheader">{{ $weekday }}</div>
            @endforeach
        </div>
        @foreach ($w['month_weeks'] ?? [] as $week)
            <div class="ark-cal-month__week" role="row">
                @foreach ($week['days'] as $day)
                    @php
                        $dayViewUrl = route('operations.appointments.index', array_merge(
                            array_diff_key($queryBase, ['view' => true]),
                            ['day' => $day['date'], 'view' => 'day'],
                        ));
                        $extraCount = max(0, (int) $day['count'] - 3);
                        $shownCount = min(3, (int) $day['count']);
                        $loadPressure = ! empty($day['load_pressure']);
                        $loadStatus = $day['load_status'] ?? null;
                        $loadLabel = $day['load_label'] ?? null;
                    @endphp
                    <div
                        @class([
                            'ark-cal-month__day',
                            'is-outside' => empty($day['in_month']),
                            'is-focus' => $day['date'] === $w['focus_date'],
                            'is-beyond' => $loadStatus === 'beyond_target',
                            'is-over' => $loadStatus === 'overpacked',
                            'is-empty' => (int) $day['count'] === 0,
                        ])
                        role="gridcell"
                        data-schedule-month-day
                    >
                        <div class="ark-cal-month__chrome">
                            <a href="{{ $dayViewUrl }}" class="fw-bold" title="Open {{ $day['date'] }} day view"><span class="d-md-none">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D') }} </span>{{ $day['day_label'] }}</a>
                            @if (filled($loadLabel))
                                <span
                                    class="badge {{ $loadPressure ? 'bg-warning-lt' : 'bg-secondary-lt' }}"
                                    title="{{ $loadPressure ? 'Near or over soft capacity' : 'Appointments this day' }}"
                                    data-schedule-month-count
                                >{{ $loadLabel }}</span>
                            @endif
                        </div>
                        <ul class="list-unstyled mb-0 min-w-0">
                            @foreach (array_slice($day['cards'], 0, 3) as $card)
                                @php
                                    $appointmentId = (int) ($card['id'] ?? 0);
                                    $roNumber = $card['repair_order_number'] ?? null;
                                    $headline = filled($roNumber)
                                        ? '#'.$roNumber.' · '.$card['customer_name']
                                        : $card['customer_name'];
                                @endphp
                                <li class="ark-cal-event" :class="{ 'is-open': openAppointmentId === {{ $appointmentId }} }" data-schedule-event>
                                    <button
                                        type="button"
                                        class="ark-cal-event__btn"
                                        data-appointment-id="{{ $appointmentId }}"
                                        @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                        :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                    >
                                        <span class="d-block fw-medium text-truncate">{{ $headline }}</span>
                                        @if (filled($card['vehicle_label'] ?? null))
                                            <span class="d-block small text-truncate">{{ $card['vehicle_label'] }}</span>
                                        @endif
                                        <span class="d-block small text-secondary">{{ $card['time_label'] }}</span>
                                    </button>

                                    @include('operations.appointments.partials.appointment-detail-popover', [
                                        'card' => $card,
                                    ])
                                </li>
                            @endforeach
                            @if ($extraCount > 0)
                                <li class="small px-1">
                                    <a href="{{ $dayViewUrl }}">{{ $shownCount }} shown · +{{ $extraCount }} more</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
