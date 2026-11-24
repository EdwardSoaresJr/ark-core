<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;
use App\Ark\Operations\Workboard\JobBoardListPageBuilder;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use App\Ark\Runtime\Preferences\UiLayoutModule;
use App\Ark\Runtime\Preferences\UiLayoutPreference;
use App\Ark\Runtime\Preferences\UiPreferenceScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsHomeController
{
    public function __invoke(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        JobBoardListPageBuilder $listPageBuilder,
    ): View {
        $layoutPreference = UiLayoutPreference::resolve($request, UiLayoutModule::JobBoard);
        $preferenceScope = UiPreferenceScope::forRequest($request);

        $repairOrders = $repairOrderQuery->forAdvisor();
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
            'jobBoardListPage' => $listPage,
            'jobBoardListRows' => $listPage->rows,
            'jobBoardLaneTabs' => $listPage->laneTabs,
            'jobBoardLayoutMode' => $layoutPreference['mode'],
            'jobBoardLayoutExplicit' => $layoutPreference['explicit'],
            'jobBoardLayout' => $layoutPreference['layout'],
            'uiPreferenceScope' => $preferenceScope,
        ]);
    }
}
