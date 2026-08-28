@php
    $queryBase = $queryBase ?? [];
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<div class="ops-board-shell ops-cal-month" role="grid" aria-label="Month schedule">
    <div class="ops-cal-month__head" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;">
        @foreach ($weekdays as $weekday)
            <p>{{ $weekday }}</p>
        @endforeach
    </div>
    @foreach ($w['month_weeks'] ?? [] as $week)
        <div class="ops-cal-month__week" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;">
            @foreach ($week['days'] as $day)
                <a
                    href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $day['date']])) }}"
                    class="ops-cal-month__day {{ ! empty($day['in_month']) ? '' : 'ops-cal-month__day--outside' }} {{ $day['date'] === $w['focus_date'] ? 'ops-cal-month__day--focus' : '' }}"
                >
                    <span class="ops-cal-month__date">{{ $day['day_label'] }}</span>
                    @if ($day['count'] > 0)
                        <span class="ops-cal-month__count">{{ $day['count'] }}</span>
                    @endif
                    <ul class="ops-cal-month__list">
                        @foreach (array_slice($day['cards'], 0, 3) as $card)
                            <li class="truncate">{{ $card['time_label'] }} {{ $card['customer_name'] }}</li>
                        @endforeach
                    </ul>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
