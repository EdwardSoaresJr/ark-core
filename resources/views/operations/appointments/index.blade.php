<x-operations.app title="Schedule" ui="tabler">
    @php
        $w = $workspace;
        $pxPerMinute = 1.35;
        $gridHeight = (int) round(($w['grid_minutes'] ?? 540) * $pxPerMinute);
        $boardView = $w['view'] ?? 'day';
        $queryBase = array_filter([
            'day' => $w['focus_date'],
            'view' => $boardView,
            'lens' => ($w['lens'] ?? 'agenda') !== 'agenda' ? ($w['lens'] ?? null) : null,
            'allocate' => ($boardView === 'week' && ($w['allocate'] ?? 'day') !== 'day') ? ($w['allocate'] ?? null) : null,
        ], fn ($v) => $v !== null && $v !== '');
        $countLabel = $w['total_count'].' appointment'.($w['total_count'] === 1 ? '' : 's')
            .((($w['lens'] ?? 'agenda') !== 'agenda' && ($w['agenda_count'] ?? 0) !== ($w['total_count'] ?? 0)) ? ' · '.$w['agenda_count'].' on this day' : '');
    @endphp

    <x-tabler.page layout="fluid" :title="$w['focus_label']" pretitle="Schedule" :subtitle="$countLabel" actions-label="Schedule navigation">
        <x-slot:actions>
            <form method="POST" action="{{ route('operations.appointments.board-view') }}" class="btn-group" role="group" aria-label="Schedule view" data-schedule-view-switch>
                @csrf
                <input type="hidden" name="day" value="{{ $w['focus_date'] }}">
                @if (($w['lens'] ?? 'agenda') !== 'agenda')
                    <input type="hidden" name="lens" value="{{ $w['lens'] }}">
                @endif
                @if ($boardView === 'week' && ($w['allocate'] ?? 'day') !== 'day')
                    <input type="hidden" name="allocate" value="{{ $w['allocate'] }}">
                @endif
                @foreach ($w['view_options'] ?? [] as $option)
                    <button
                        type="submit"
                        name="view"
                        value="{{ $option['key'] }}"
                        @class(['btn', 'btn-primary' => ! empty($option['selected'])])
                        @if (! empty($option['selected'])) aria-current="true" @endif
                    >{{ $option['label'] }}</button>
                @endforeach
            </form>
            <div class="btn-group" role="group" aria-label="Move through the schedule">
                <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['nav_prev_date']])) }}" class="btn btn-icon" aria-label="Previous" title="Previous">
                    <x-tabler.icon name="arrow-left" />
                </a>
                <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['today_date']])) }}" class="btn">Today</a>
                <a href="{{ route('operations.appointments.index', array_merge($queryBase, ['day' => $w['nav_next_date']])) }}" class="btn btn-icon" aria-label="Next" title="Next">
                    <x-tabler.icon name="arrow-right" />
                </a>
            </div>
            <a href="{{ route('operations.schedule') }}" class="btn btn-primary">
                <x-tabler.icon name="plus" />
                Schedule
            </a>
        </x-slot:actions>

        <div>
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if (session('schedule_warnings'))
                <div class="alert alert-warning" role="status">
                    @foreach ((array) session('schedule_warnings') as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            @if ($boardView === 'week')
                <nav class="nav nav-pills mb-2" aria-label="Week allocation">
                    @foreach ($w['allocate_options'] ?? [] as $option)
                        <a
                            href="{{ route('operations.appointments.index', array_filter([
                                'day' => $w['focus_date'],
                                'view' => 'week',
                                'allocate' => $option['key'] !== 'day' ? $option['key'] : null,
                                'lens' => ($w['lens'] ?? 'agenda') !== 'agenda' ? ($w['lens'] ?? null) : null,
                            ])) }}"
                            @class(['nav-link', 'active' => ! empty($option['selected'])])
                            @if (! empty($option['selected'])) aria-current="true" @endif
                        >{{ $option['label'] }}</a>
                    @endforeach
                </nav>
            @endif
            @if (! empty($w['chips']) && count($w['chips']) > 1)
                <nav class="nav nav-pills mb-2" aria-label="Day schedule perspectives" data-schedule-lenses>
                    @foreach ($w['chips'] as $chip)
                        <a
                            href="{{ route('operations.appointments.index', array_filter([
                                'day' => $w['focus_date'],
                                'view' => $boardView,
                                'lens' => $chip['key'] !== 'agenda' ? $chip['key'] : null,
                                'allocate' => ($boardView === 'week' && ($w['allocate'] ?? 'day') !== 'day') ? ($w['allocate'] ?? null) : null,
                            ])) }}"
                            @class(['nav-link', 'active' => ! empty($chip['selected'])])
                            @if (! empty($chip['selected'])) aria-current="true" @endif
                        >
                            {{ $chip['label'] }}
                            <span class="badge ms-2 {{ ! empty($chip['selected']) ? 'bg-white text-primary' : 'bg-secondary-lt' }}">{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="d-flex flex-column gap-3">
            @include('operations.appointments.partials.request-availability-day', [
                'w' => $w,
                'requestDayStatus' => $requestDayStatus ?? ['requestable' => false, 'weekly_enabled' => false, 'exception' => null],
            ])

            @include('operations.appointments.partials.capacity-rail', ['rail' => $w['capacity_rail']])

            @if ($boardView === 'month')
                @include('operations.appointments.partials.board-month', ['w' => $w, 'queryBase' => $queryBase])
            @elseif ($boardView === 'day')
                @include('operations.appointments.partials.board-day', ['w' => $w, 'pxPerMinute' => $pxPerMinute, 'gridHeight' => $gridHeight])
            @else
                @include('operations.appointments.partials.board-week', ['w' => $w, 'queryBase' => $queryBase])
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
