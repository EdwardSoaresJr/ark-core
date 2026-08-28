<x-operations.app title="Schedule">
    @php
        $w = $workspace;
        $pxPerMinute = 1.35;
        $gridHeight = (int) round(($w['grid_minutes'] ?? 540) * $pxPerMinute);
        $boardView = $w['view'] ?? 'day';
        $queryBase = array_filter([
            'day' => $w['focus_date'],
            'view' => $boardView,
            'lens' => ($w['lens'] ?? 'agenda') !== 'agenda' ? ($w['lens'] ?? null) : null,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp

    <section class="ops-index space-y-3">
        @if (session('status'))
            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950">{{ session('status') }}</div>
        @endif
        @if (session('schedule_warnings'))
            <div class="border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950 space-y-1">
                @foreach ((array) session('schedule_warnings') as $warning)
                    <p>{{ $warning }}</p>
                @endforeach
            </div>
        @endif
        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="ops-board-shell">
            <div class="ops-page-toolbar flex-wrap gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Schedule</p>
                    <h2 class="text-base font-black text-slate-950">{{ $w['focus_label'] }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $w['total_count'] }} appointment{{ $w['total_count'] === 1 ? '' : 's' }}
                        @if (($w['lens'] ?? 'agenda') !== 'agenda' && ($w['agenda_count'] ?? 0) !== ($w['total_count'] ?? 0))
                            <span class="text-slate-400">· {{ $w['agenda_count'] }} on this day</span>
                        @endif
                    </p>
                </div>
                <div class="ops-page-toolbar-actions">
                    <form method="POST" action="{{ route('operations.appointments.board-view') }}" class="ops-cal-view-switch" role="group" aria-label="Schedule view">
                        @csrf
                        <input type="hidden" name="day" value="{{ $w['focus_date'] }}">
                        @if (($w['lens'] ?? 'agenda') !== 'agenda')
                            <input type="hidden" name="lens" value="{{ $w['lens'] }}">
                        @endif
                        @foreach ($w['view_options'] ?? [] as $option)
                            <button
                                type="submit"
                                name="view"
                                value="{{ $option['key'] }}"
                                class="ops-page-link {{ ! empty($option['selected']) ? 'ops-page-link--primary' : '' }}"
                                @if (! empty($option['selected'])) aria-current="true" @endif
                            >{{ $option['label'] }}</button>
                        @endforeach
                    </form>
                    <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['nav_prev_date']])) }}" class="ops-page-link">Prev</a>
                    <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['today_date']])) }}" class="ops-page-link">Today</a>
                    <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['nav_next_date']])) }}" class="ops-page-link">Next</a>
                    <a href="{{ route('operations.schedule') }}" class="ops-page-link ops-page-link--primary">Schedule</a>
                </div>
            </div>
            @if (! empty($w['chips']) && count($w['chips']) > 1)
                <div class="ops-day-lens" role="navigation" aria-label="Day schedule perspectives">
                    @foreach ($w['chips'] as $chip)
                        <a
                            href="{{ route('operations.appointments.index', array_filter([
                                'day' => $w['focus_date'],
                                'view' => $boardView,
                                'lens' => $chip['key'] !== 'agenda' ? $chip['key'] : null,
                            ])) }}"
                            class="ops-day-lens__chip {{ ! empty($chip['selected']) ? 'ops-day-lens__chip--selected' : '' }}"
                            @if (! empty($chip['selected'])) aria-current="true" @endif
                        >
                            <span class="ops-day-lens__label">{{ $chip['label'] }}</span>
                            <span class="ops-day-lens__count">{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @include('operations.appointments.partials.request-availability-day', [
            'w' => $w,
            'requestDayStatus' => $requestDayStatus ?? ['requestable' => false, 'weekly_enabled' => false, 'exception' => null],
        ])

        <div class="ops-cal-layout">
            @include('operations.appointments.partials.capacity-rail', ['rail' => $w['capacity_rail']])

            <div class="ops-cal-board">
                @if ($boardView === 'month')
                    @include('operations.appointments.partials.board-month', ['w' => $w, 'queryBase' => $queryBase])
                @elseif ($boardView === 'day')
                    @include('operations.appointments.partials.board-day', ['w' => $w, 'pxPerMinute' => $pxPerMinute, 'gridHeight' => $gridHeight])
                @else
                    @include('operations.appointments.partials.board-week', ['w' => $w, 'queryBase' => $queryBase])
                @endif
            </div>
        </div>
    </section>
</x-operations.app>
