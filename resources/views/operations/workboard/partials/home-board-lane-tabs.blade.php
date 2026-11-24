@php
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage|null $jobBoardListPage */
    $isListLayout = (bool) ($isListLayout ?? false);
    $activeLane = $isListLayout ? ($jobBoardListPage?->laneKey ?? 'all') : null;
@endphp

@if ($isListLayout)
    <nav
        class="nav nav-pills flex-nowrap overflow-auto mt-2 gap-1"
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
                @class(['nav-link text-nowrap', 'active' => $isActive])
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
@else
    <div
        class="nav nav-pills flex-nowrap overflow-auto mt-2 gap-1"
        role="toolbar"
        aria-label="Job board queues"
        data-job-board-lane-tabs
    >
        @foreach ($jobBoardLaneTabs as $tab)
            @php
                $tabTone = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                    \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($tab['color']),
                );
            @endphp
            <button
                type="button"
                class="nav-link text-nowrap"
                :class="{ 'active': laneKey === '{{ $tab['key'] }}' }"
                :aria-pressed="laneKey === '{{ $tab['key'] }}' ? 'true' : 'false'"
                @click="laneKey = '{{ $tab['key'] }}'"
                data-job-board-lane-tab="{{ $tab['key'] }}"
                data-lane-color="{{ $tab['color'] }}"
            >
                <span class="status-dot status-{{ $tabTone }} me-1" aria-hidden="true"></span>
                {{ $tab['label'] }}
                <span
                    class="badge bg-secondary-lt text-secondary ms-1"
                    data-job-board-lane-count="{{ $tab['key'] }}"
                    title="Total in this queue"
                >{{ $tab['count'] }}</span>
            </button>
        @endforeach
    </div>
@endif
