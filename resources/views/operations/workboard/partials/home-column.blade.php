@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageLaneProjection $column */
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    $isConstraintColumn = $cockpit->constraintColumnKey === $column->key;
    $inventoryUrl = $column->inventoryUrl && $column->totalCount > 0 ? $column->inventoryUrl : null;
    $laneColor = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
        \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($column->color),
    );
@endphp

<section
    id="ops-home-col-{{ $column->key }}"
    class="d-flex flex-column gap-2"
    data-lane-key="{{ $column->key }}"
    data-lane-color="{{ $column->color }}"
    @if ($isConstraintColumn) data-lane-constraint @endif
    aria-labelledby="ops-home-col-{{ $column->key }}-title"
>
    <div class="card card-sm">
        <div class="card-status-top bg-{{ $laneColor }}"></div>
        <div class="card-body py-2">
            <h2 class="card-title mb-0" id="ops-home-col-{{ $column->key }}-title">
                @if ($inventoryUrl)
                    <a href="{{ $inventoryUrl }}" class="text-reset">{{ $column->label }}</a>
                @else
                    {{ $column->label }}
                @endif
                <span class="text-secondary fw-normal" x-text="'(' + columnVisibleCount('{{ $column->key }}') + ')'">({{ $column->totalCount }})</span>
            </h2>
        </div>
    </div>

    @foreach ($column->visibleCards as $card)
        @include('operations.workboard.partials.home-card', [
            'card' => $card,
            'columnTone' => $column->tone,
            'repairOrderTotals' => $repairOrderTotals,
            'homeCardSurfaces' => $homeCardSurfaces,
            'recommendedRepairOrderId' => $cockpit->recommendedRepairOrderId,
        ])
    @endforeach

    @if ($column->hiddenCount > 0 && $column->viewAllUrl)
        <a href="{{ $column->viewAllUrl }}" class="btn btn-ghost-secondary w-100">+{{ $column->hiddenCount }} more</a>
    @endif
</section>
