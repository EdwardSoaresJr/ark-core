<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageLaneProjection;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class OperationsHomeController
{
    public function __invoke(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $workboardTriage,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
    ): View {
        $repairOrders = $repairOrderQuery->forAdvisor();
        $homeBoardColumns = $workboardTriage->forAdvisorHomeBoard($repairOrders);
        $visibleRepairOrders = new \Illuminate\Database\Eloquent\Collection(
            $this->visibleRepairOrders($homeBoardColumns)->all(),
        );

        $repairOrderTotals = $visibleRepairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
        ]);

        $cardSurfaces = $homeCardSurfaces->mapForHomeBoard($visibleRepairOrders, $homeBoardColumns, $repairOrderTotals);

        return view('operations.home', [
            'cockpit' => AdvisorHomeCockpitProjection::forJobBoard(
                $homeBoardColumns,
                $repairOrderTotals,
                $repairOrders->count(),
            ),
            'attentionZones' => [],
            'homeBoardColumns' => $homeBoardColumns,
            'homeCardSurfaces' => $cardSurfaces,
            'homeBoardTechnicians' => $homeCardSurfaces->technicianOptions($repairOrders),
            'repairOrderTotals' => $repairOrderTotals,
            'activeRepairOrderCount' => $repairOrders->count(),
        ]);
    }

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return Collection<int, RepairOrder>
     */
    private function visibleRepairOrders(array $homeBoardColumns): Collection
    {
        return collect($homeBoardColumns)
            ->flatMap(fn (WorkboardTriageLaneProjection $column): array => $column->visibleCards)
            ->map(fn (WorkboardTriageCard $card): RepairOrder => $card->repairOrder)
            ->unique(fn (RepairOrder $repairOrder): int => $repairOrder->id)
            ->values();
    }
}
