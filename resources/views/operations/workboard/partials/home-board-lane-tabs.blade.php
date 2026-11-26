@php
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    $activeLane = $jobBoardListPage->laneKey;
@endphp

<nav
    class="ark-job-board-lane-tabs"
    aria-label="Job board queues"
    data-job-board-lane-tabs
>
    <div class="ark-job-board-lane-tabs__track" data-job-board-lane-tabs-track>
        @foreach ($jobBoardLaneTabs as $tab)
            @php
                $tabTone = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                    \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($tab['color']),
                );
                $tabUrl = route('operations.index', $jobBoardListPage->queryParams([
                    'lane' => $tab['key'],
                    'page' => 1,
                ]));
                $isActive = $activeLane === $tab['key'];
            @endphp
            <a
                href="{{ $tabUrl }}"
                @class([
                    'ark-job-board-lane-tabs__tab',
                    'nav-link',
                    'active' => $isActive,
                ])
                data-job-board-lane-tab="{{ $tab['key'] }}"
                data-lane-color="{{ $tab['color'] }}"
                data-lane-tone="{{ $tabTone }}"
                @if ($isActive) aria-current="page" @endif
            >
                <span class="status-dot status-{{ $tabTone }}" aria-hidden="true"></span>
                <span class="ark-job-board-lane-tabs__label">{{ $tab['label'] }}</span>
                <span
                    class="ark-job-board-lane-tabs__count"
                    data-job-board-lane-count="{{ $tab['key'] }}"
                    title="Total in this queue"
                >{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
