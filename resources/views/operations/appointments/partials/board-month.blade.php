@php
    $queryBase = $queryBase ?? [];
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<div
    class="ops-board-shell ops-cal-month"
    role="grid"
    aria-label="Month schedule"
    x-data="arkScheduleAppointmentPopover"
    :style="{ '--ops-cal-pop-top': popTop + 'px', '--ops-cal-pop-left': popLeft + 'px' }"
    @click="close()"
    @click.outside="close()"
    @keydown.escape.window="close()"
    @scroll.window="reposition()"
    @resize.window="reposition()"
>
    <div class="ops-cal-month__head" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;">
        @foreach ($weekdays as $weekday)
            <p>{{ $weekday }}</p>
        @endforeach
    </div>
    @foreach ($w['month_weeks'] ?? [] as $week)
        <div class="ops-cal-month__week" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;">
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
                    $dayLoadClass = match ($loadStatus) {
                        'beyond_target' => 'ops-cal-month__day--beyond',
                        'overpacked' => 'ops-cal-month__day--over',
                        default => '',
                    };
                @endphp
                <div
                    class="ops-cal-month__day {{ ! empty($day['in_month']) ? '' : 'ops-cal-month__day--outside' }} {{ $day['date'] === $w['focus_date'] ? 'ops-cal-month__day--focus' : '' }} {{ $dayLoadClass }}"
                    role="gridcell"
                >
                    <div class="ops-cal-month__day-chrome">
                        <a
                            href="{{ $dayViewUrl }}"
                            class="ops-cal-month__date"
                            title="Open {{ $day['date'] }} day view"
                        >{{ $day['day_label'] }}</a>
                        @if (filled($loadLabel))
                            <span
                                class="ops-cal-month__count {{ $loadPressure ? 'ops-cal-month__count--pressure' : '' }}"
                                title="{{ $loadPressure ? 'Near or over soft capacity' : 'Appointments this day' }}"
                            >{{ $loadLabel }}</span>
                        @endif
                    </div>
                    <ul class="ops-cal-month__list">
                        @foreach (array_slice($day['cards'], 0, 3) as $card)
                            @php
                                $appointmentId = (int) ($card['id'] ?? 0);
                                $roNumber = $card['repair_order_number'] ?? null;
                                $headline = filled($roNumber)
                                    ? '#'.$roNumber.' · '.$card['customer_name']
                                    : $card['customer_name'];
                            @endphp
                            <li
                                class="ops-cal-month__event"
                                :class="{ 'ops-cal-appt-event--open': openAppointmentId === {{ $appointmentId }} }"
                            >
                                <button
                                    type="button"
                                    class="ops-cal-month__event-btn"
                                    data-appointment-id="{{ $appointmentId }}"
                                    @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                    :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                >
                                    <span class="ops-cal-month__event-headline truncate">{{ $headline }}</span>
                                    @if (filled($card['vehicle_label'] ?? null))
                                        <span class="ops-cal-month__event-vehicle truncate">{{ $card['vehicle_label'] }}</span>
                                    @endif
                                    <span class="ops-cal-month__event-time">{{ $card['time_label'] }}</span>
                                </button>

                                @include('operations.appointments.partials.appointment-detail-popover', [
                                    'card' => $card,
                                ])
                            </li>
                        @endforeach
                        @if ($extraCount > 0)
                            <li class="ops-cal-month__more">
                                <a href="{{ $dayViewUrl }}">{{ $shownCount }} shown · +{{ $extraCount }} more</a>
                            </li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
