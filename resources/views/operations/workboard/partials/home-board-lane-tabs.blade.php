@php
    /** @var list<array{key: string, label: string, color: string, count: int}> $jobBoardLaneTabs */
@endphp

<nav
    class="nav nav-tabs flex-nowrap overflow-auto mt-2"
    role="tablist"
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
            role="tab"
            class="nav-link text-nowrap"
            :class="{ 'active': laneKey === '{{ $tab['key'] }}' }"
            :aria-selected="laneKey === '{{ $tab['key'] }}' ? 'true' : 'false'"
            @click="laneKey = '{{ $tab['key'] }}'"
            data-job-board-lane-tab="{{ $tab['key'] }}"
            data-lane-color="{{ $tab['color'] }}"
        >
            <span class="status-dot status-{{ $tabTone }} me-1" aria-hidden="true"></span>
            {{ $tab['label'] }}
            <span
                class="badge bg-secondary-lt text-secondary ms-1"
                data-job-board-lane-count="{{ $tab['key'] }}"
                @if ($isListLayout ?? false)
                    x-text="filteredLaneCount('{{ $tab['key'] }}')"
                @endif
            >{{ $tab['count'] }}</span>
        </button>
    @endforeach
</nav>
