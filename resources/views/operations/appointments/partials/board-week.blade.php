@php
    $queryBase = $queryBase ?? [];
    $allocation = $w['week_allocation'] ?? null;
    $assignOptions = $w['assign_options'] ?? ['technicians' => [], 'workstations' => []];
    $allocate = $w['allocate'] ?? 'day';
    $assignContext = [
        'day' => $w['focus_date'],
        'view' => 'week',
        'allocate' => $allocate !== 'day' ? $allocate : null,
        'lens' => $w['lens'] ?? 'agenda',
    ];
@endphp

@if ($allocation !== null)
    <div
        class="ops-board-shell ops-cal-week-alloc"
        role="grid"
        aria-label="Week resource allocation"
        x-data="arkScheduleAppointmentPopover"
        :style="{ '--ops-cal-pop-top': popTop + 'px', '--ops-cal-pop-left': popLeft + 'px' }"
        @click="close()"
        @click.outside="close()"
        @keydown.escape.window="close()"
        @scroll.window="reposition()"
        @resize.window="reposition()"
    >
        <div class="ops-cal-week-alloc__scroll">
            <div class="ops-cal-week-alloc__grid">
                <div class="ops-cal-week-alloc__corner" aria-hidden="true"></div>
                @foreach ($allocation['day_headers'] as $header)
                    @php
                        $status = $header['capacity_status'] ?? null;
                        $headerClass = match ($status) {
                            'beyond_target' => 'ops-cal-week-alloc__day-head--beyond',
                            'overpacked' => 'ops-cal-week-alloc__day-head--over',
                            default => '',
                        };
                    @endphp
                    <a
                        href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $header['date'], 'view' => 'day'])) }}"
                        class="ops-cal-week-alloc__day-head {{ $headerClass }}"
                    >
                        <span class="ops-cal-week-alloc__day-label">{{ $header['day_label'] }}</span>
                    </a>
                @endforeach

                @foreach ($allocation['rows'] as $row)
                    <div class="ops-cal-week-alloc__resource {{ $row['resource_id'] === null ? 'ops-cal-week-alloc__resource--unassigned' : '' }}">
                        <p class="ops-cal-week-alloc__resource-name">{{ $row['label'] }}</p>
                        <p class="ops-cal-week-alloc__resource-meta">
                            {{ $row['week_count'] }} · {{ $row['labor_label'] }}
                        </p>
                    </div>
                    @foreach ($row['days'] as $day)
                        <div class="ops-cal-week-alloc__cell {{ $day['date'] === $w['focus_date'] ? 'ops-cal-week-alloc__cell--focus' : '' }}">
                            <ul class="ops-cal-week-alloc__list">
                                @forelse ($day['cards'] as $card)
                                    @php
                                        $appointmentId = (int) ($card['id'] ?? 0);
                                        $roNumber = $card['repair_order_number'] ?? null;
                                        $headline = filled($roNumber)
                                            ? '#'.$roNumber.' · '.$card['customer_name']
                                            : $card['customer_name'];
                                    @endphp
                                    <li
                                        class="ops-cal-week-alloc__event"
                                        :class="{ 'ops-cal-appt-event--open': openAppointmentId === {{ $appointmentId }} }"
                                    >
                                        <button
                                            type="button"
                                            class="ops-cal-week-alloc__event-btn"
                                            @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                            :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                        >
                                            <span class="ops-cal-week-alloc__event-time">{{ $card['time_label'] }}</span>
                                            <span class="ops-cal-week-alloc__event-name truncate">{{ $headline }}</span>
                                            @if (filled($card['vehicle_label'] ?? null))
                                                <span class="ops-cal-week-alloc__event-vehicle truncate">{{ $card['vehicle_label'] }}</span>
                                            @endif
                                            @if (filled($card['concern'] ?? null))
                                                <span class="ops-cal-week-alloc__event-concern truncate">{{ $card['concern'] }}</span>
                                            @endif
                                        </button>

                                        @include('operations.appointments.partials.appointment-detail-popover', [
                                            'card' => $card,
                                            'showAssignment' => true,
                                            'assignOptions' => $assignOptions,
                                            'assignContext' => $assignContext,
                                        ])
                                    </li>
                                @empty
                                    <li class="ops-cal-week-alloc__empty">-</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
@else
    <div
        class="ops-board-shell ops-cal-week-board"
        role="grid"
        aria-label="Week schedule"
        x-data="arkScheduleAppointmentPopover"
        :style="{ '--ops-cal-pop-top': popTop + 'px', '--ops-cal-pop-left': popLeft + 'px' }"
        @click="close()"
        @click.outside="close()"
        @keydown.escape.window="close()"
        @scroll.window="reposition()"
        @resize.window="reposition()"
    >
        <div class="ops-cal-week ops-cal-week--board" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;">
            @foreach ($w['week_days'] as $day)
                <section class="ops-cal-week__day {{ $day['date'] === $w['focus_date'] ? 'ops-cal-week__day--focus' : '' }}">
                    <a
                        href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $day['date']])) }}"
                        class="ops-cal-week__heading"
                    >
                        <p class="ops-cal-week__label">{{ $day['day_label'] }}</p>
                        <p class="ops-cal-week__count">{{ $day['count'] }}</p>
                    </a>
                    <ul class="ops-cal-week__list">
                        @forelse ($day['cards'] as $card)
                            @php
                                $appointmentId = (int) ($card['id'] ?? 0);
                                $roNumber = $card['repair_order_number'] ?? null;
                                $headline = filled($roNumber)
                                    ? '#'.$roNumber.' · '.$card['customer_name']
                                    : $card['customer_name'];
                            @endphp
                            <li
                                class="ops-cal-week__event"
                                :class="{ 'ops-cal-appt-event--open': openAppointmentId === {{ $appointmentId }} }"
                            >
                                <button
                                    type="button"
                                    class="ops-cal-week__item"
                                    @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                    :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                >
                                    <span class="ops-cal-week__item-time">{{ $card['time_label'] }}</span>
                                    <span class="ops-cal-week__item-name">{{ $headline }}</span>
                                </button>

                                @include('operations.appointments.partials.appointment-detail-popover', [
                                    'card' => $card,
                                ])
                            </li>
                        @empty
                            <li class="ops-cal-week__empty">Open</li>
                        @endforelse
                    </ul>
                    <a
                        href="{{ route('operations.schedule', ['starts_at' => $day['date'].'T08:00']) }}"
                        class="ops-cal-week__add"
                    >Schedule</a>
                </section>
            @endforeach
        </div>
    </div>
@endif
