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
        class="card"
        x-data="arkScheduleAppointmentPopover"
        :style="{ '--ark-cal-pop-top': popTop + 'px', '--ark-cal-pop-left': popLeft + 'px' }"
        @click="close()"
        @click.outside="close()"
        @keydown.escape.window="close()"
        @scroll.window="reposition()"
        @scroll.capture="reposition()"
        @resize.window="reposition()"
        data-schedule-board="week-allocation"
    >
        <div class="overflow-auto">
            <div class="ark-cal-alloc" role="grid" aria-label="Week resource allocation">
                <div class="ark-cal-alloc__resource" aria-hidden="true"></div>
                @foreach ($allocation['day_headers'] as $header)
                    @php $status = $header['capacity_status'] ?? null; @endphp
                    <a
                        href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $header['date'], 'view' => 'day'])) }}"
                        @class([
                            'ark-cal-alloc__head subheader',
                            'text-danger' => $status === 'beyond_target',
                            'text-warning' => $status === 'overpacked',
                        ])
                    >{{ $header['day_label'] }}</a>
                @endforeach

                @foreach ($allocation['rows'] as $row)
                    <div class="ark-cal-alloc__resource">
                        <div @class(['fw-bold text-truncate', 'text-secondary' => $row['resource_id'] === null])>{{ $row['label'] }}</div>
                        <div class="small text-secondary">{{ $row['week_count'] }} · {{ $row['labor_label'] }}</div>
                    </div>
                    @foreach ($row['days'] as $day)
                        <div @class(['ark-cal-alloc__cell', 'is-focus' => $day['date'] === $w['focus_date']])>
                            <ul class="list-unstyled mb-0">
                                @forelse ($day['cards'] as $card)
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
                                            @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                            :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                        >
                                            <span class="d-block small text-secondary">{{ $card['time_label'] }}</span>
                                            <span class="d-block fw-medium text-truncate">{{ $headline }}</span>
                                            @if (filled($card['vehicle_label'] ?? null))
                                                <span class="d-block small text-truncate">{{ $card['vehicle_label'] }}</span>
                                            @endif
                                            @if (filled($card['concern'] ?? null))
                                                <span class="d-block small text-secondary text-truncate">{{ $card['concern'] }}</span>
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
                                    <li class="text-secondary small px-1">-</li>
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
        class="card"
        x-data="arkScheduleAppointmentPopover"
        :style="{ '--ark-cal-pop-top': popTop + 'px', '--ark-cal-pop-left': popLeft + 'px' }"
        @click="close()"
        @click.outside="close()"
        @keydown.escape.window="close()"
        @scroll.window="reposition()"
        @scroll.capture="reposition()"
        @resize.window="reposition()"
        data-schedule-board="week"
    >
        <div class="ark-cal-week" role="grid" aria-label="Week schedule">
            @foreach ($w['week_days'] as $day)
                <section @class(['ark-cal-week__day', 'is-focus' => $day['date'] === $w['focus_date']])>
                    <a
                        href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $day['date']])) }}"
                        class="ark-cal-week__head"
                    >
                        <span class="subheader">{{ $day['day_label'] }}</span>
                        <span class="badge {{ $day['count'] > 0 ? 'bg-primary-lt' : 'bg-secondary-lt' }}">{{ $day['count'] }}</span>
                    </a>
                    <ul class="list-unstyled flex-fill mb-0 p-1">
                        @forelse ($day['cards'] as $card)
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
                                    @click.stop="toggle({{ $appointmentId }}, $event.currentTarget)"
                                    :aria-expanded="(openAppointmentId === {{ $appointmentId }}).toString()"
                                >
                                    <span class="small text-secondary text-nowrap">{{ $card['time_label'] }}</span>
                                    <span class="fw-medium">{{ $headline }}</span>
                                </button>

                                @include('operations.appointments.partials.appointment-detail-popover', [
                                    'card' => $card,
                                ])
                            </li>
                        @empty
                            <li class="small text-secondary px-1">Open</li>
                        @endforelse
                    </ul>
                    <a
                        href="{{ route('operations.schedule', ['starts_at' => $day['date'].'T08:00']) }}"
                        class="ark-cal-week__add small"
                    >
                        <x-tabler.icon name="plus" />
                        Schedule
                    </a>
                </section>
            @endforeach
        </div>
    </div>
@endif
