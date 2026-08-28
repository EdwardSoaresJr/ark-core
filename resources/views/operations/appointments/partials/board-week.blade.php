@php
    $queryBase = $queryBase ?? [];
@endphp

<div class="ops-board-shell ops-cal-week-board" role="grid" aria-label="Week schedule">
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
                        <li>
                            <a href="{{ $card['show_url'] }}?edit=1" class="ops-cal-week__item">
                                <span class="ops-cal-week__item-time">{{ $card['time_label'] }}</span>
                                <span class="ops-cal-week__item-name">{{ $card['customer_name'] }}</span>
                            </a>
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
