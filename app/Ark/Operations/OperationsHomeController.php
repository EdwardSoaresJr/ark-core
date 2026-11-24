<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;
use App\Ark\Operations\Workboard\JobBoardListPageBuilder;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageLaneProjection;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
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
        JobBoardListPageBuilder $listPageBuilder,
    ): View {
        $layout = JobBoardLayoutPreference::resolve($request);
        $repairOrders = $repairOrderQuery->forAdvisor();

        if ($layout->isList()) {
            $listPage = $listPageBuilder->build($request, $repairOrders);
            $pageRepairOrders = new \Illuminate\Database\Eloquent\Collection($listPage->pageRepairOrders->all());
            $repairOrderTotals = $pageRepairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
                $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
            ]);

            return view('operations.home', [
                'cockpit' => AdvisorHomeCockpitProjection::forJobBoard(
                    $listPage->pageColumns,
                    $repairOrderTotals,
                    $repairOrders->count(),
                ),
                'attentionZones' => [],
                'homeBoardColumns' => $listPage->pageColumns,
                'homeCardSurfaces' => [],
                'homeBoardTechnicians' => $homeCardSurfaces->technicianOptions($repairOrders),
                'repairOrderTotals' => $repairOrderTotals,
                'activeRepairOrderCount' => $repairOrders->count(),
                'jobBoardLayout' => $layout,
                'jobBoardListPage' => $listPage,
                'jobBoardListRows' => $listPage->rows,
                'jobBoardLaneTabs' => $listPage->laneTabs,
            ]);
        }

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
            'jobBoardLayout' => $layout,
            'jobBoardListPage' => null,
            'jobBoardListRows' => [],
            'jobBoardLaneTabs' => $this->boardLaneTabs($homeBoardColumns),
        ]);
    }

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return list<array{key: string, label: string, color: string, count: int}>
     */
    private function boardLaneTabs(array $homeBoardColumns): array
    {
        $laneTotal = collect($homeBoardColumns)->sum(
            fn (WorkboardTriageLaneProjection $column): int => $column->totalCount,
        );

        $tabs = [[
            'key' => 'all',
            'label' => 'All',
            'color' => 'secondary',
            'count' => $laneTotal,
        ]];

        foreach ($homeBoardColumns as $column) {
            $tabs[] = [
                'key' => $column->key,
                'label' => $column->label,
                'color' => $column->color,
                'count' => $column->totalCount,
            ];
        }

        return $tabs;
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
