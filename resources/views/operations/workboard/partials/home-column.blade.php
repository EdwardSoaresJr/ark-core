@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageLaneProjection $column */
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    $columnStat = $cockpit->columnsByKey[$column->key] ?? null;
    $isConstraintColumn = $cockpit->constraintColumnKey === $column->key;
    $inventoryUrl = $column->inventoryUrl && $column->totalCount > 0 ? $column->inventoryUrl : null;
@endphp

<section
    id="ops-home-col-{{ $column->key }}"
    @class([
        'ops-job-board-column',
        'ops-advisor-home-board__column',
        'ops-advisor-home-board__column--constraint' => $isConstraintColumn,
    ])
>
    <header class="ops-job-board-column__header">
        <div class="ops-job-board-column__title-wrap">
            @if ($inventoryUrl)
                <a href="{{ $inventoryUrl }}" class="ops-job-board-column__title ops-job-board-column__title--link">
                    {{ $column->label }}
                    <span class="ops-job-board-column__count" x-text="'(' + columnVisibleCount('{{ $column->key }}') + ')'">
                        ({{ $column->totalCount }})
                    </span>
                </a>
            @else
                <h2 class="ops-job-board-column__title">
                    {{ $column->label }}
                    <span class="ops-job-board-column__count" x-text="'(' + columnVisibleCount('{{ $column->key }}') + ')'">
                        ({{ $column->totalCount }})
                    </span>
                </h2>
            @endif
        </div>
    </header>

    <div class="ops-radar-cards ops-advisor-home-board__cards ops-job-board-column__cards">
        @forelse ($column->visibleCards as $card)
            @include('operations.workboard.partials.home-card', [
                'card' => $card,
                'columnTone' => $column->tone,
                'repairOrderTotals' => $repairOrderTotals,
                'homeCardSurfaces' => $homeCardSurfaces,
                'recommendedRepairOrderId' => $cockpit->recommendedRepairOrderId,
            ])
        @empty
        @endforelse
    </div>
</section>
