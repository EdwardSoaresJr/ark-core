@php
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    $activeLane = $jobBoardListPage->laneKey;
@endphp

<nav
    class="nav nav-tabs flex-nowrap overflow-auto border-0 gap-0"
    aria-label="Job board queues"
    data-job-board-lane-tabs
>
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
            @class(['nav-link text-nowrap px-3', 'active' => $isActive])
            data-job-board-lane-tab="{{ $tab['key'] }}"
            data-lane-color="{{ $tab['color'] }}"
            @if ($isActive) aria-current="page" @endif
        >
            <span class="status-dot status-{{ $tabTone }} me-1" aria-hidden="true"></span>
            {{ $tab['label'] }}
            <span
                class="badge bg-secondary-lt text-secondary ms-1"
                data-job-board-lane-count="{{ $tab['key'] }}"
                title="Total in this queue"
            >{{ $tab['count'] }}</span>
        </a>
    @endforeach
</nav>
