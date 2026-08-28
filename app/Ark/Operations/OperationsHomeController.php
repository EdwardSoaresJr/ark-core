<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Today\AdvisorHomeAttentionBoardProjection;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Ark\Operations\Today\AdvisorTodayRecommendationEngine;
use App\Ark\Operations\Today\AdvisorTodayShopRadarBuilder;
use App\Ark\Operations\Today\TodayCommitmentsProjection;
use App\Ark\Operations\Today\TodayPipelineProjection;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsHomeController
{
    public function __invoke(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $workboardTriage,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorTodayRecommendationEngine $recommendationEngine,
        AdvisorTodayShopRadarBuilder $shopRadarBuilder,
        TodayPipelineProjection $pipelineProjection,
        TodayCommitmentsProjection $commitmentsProjection,
        OperationalFlowProjectionBuilder $flowProjectionBuilder,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        AdvisorHomeAttentionBoardProjection $attentionBoardProjection,
    ): View {
        $user = $request->user();
        $repairOrders = $repairOrderQuery->forAdvisor();

        $morningBrief = AdvisorTodayProjection::resolve(
            $repairOrderQuery,
            $recommendationEngine,
            $shopRadarBuilder,
            $pipelineProjection,
            $commitmentsProjection,
            $flowProjectionBuilder,
            $user,
            repairOrders: $repairOrders,
        );

        $repairOrderTotals = $repairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
        ]);

        $homeBoardColumns = $workboardTriage->forAdvisorHomeBoard($repairOrders);
        $cardSurfaces = $homeCardSurfaces->mapForHomeBoard($repairOrders, $homeBoardColumns);

        $attentionZones = $attentionBoardProjection->zones(
            $repairOrders,
            $cardSurfaces,
            $repairOrderTotals,
            recommendedRepairOrderId: ($morningBrief->briefRecommendations()[0] ?? null)?->repairOrderId,
        );

        return view('operations.home', [
            'morningBrief' => $morningBrief,
            'cockpit' => AdvisorHomeCockpitProjection::resolve(
                $morningBrief,
                $homeBoardColumns,
                $repairOrderTotals,
                $attentionZones,
            ),
            'attentionZones' => $attentionZones,
            'homeBoardColumns' => $homeBoardColumns,
            'homeCardSurfaces' => $cardSurfaces,
            'homeBoardTechnicians' => $homeCardSurfaces->technicianOptions($repairOrders),
            'repairOrderTotals' => $repairOrderTotals,
            'activeRepairOrderCount' => $repairOrders->count(),
        ]);
    }
}
