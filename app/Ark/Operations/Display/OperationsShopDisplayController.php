<?php

namespace App\Ark\Operations\Display;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Today\AdvisorHomeAttentionBoardProjection;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OperationsShopDisplayController
{
    public function index(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $workboardTriage,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        AdvisorHomeAttentionBoardProjection $attentionBoardProjection,
    ): View {
        return view('operations.display.index', [
            'display' => ShopDisplayBoardProjection::resolve(
                $repairOrderQuery,
                $workboardTriage,
                $totalsCalculator,
                $homeCardSurfaces,
                $attentionBoardProjection,
            ),
            'refreshSeconds' => 45,
        ]);
    }

    public function fragment(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $workboardTriage,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        AdvisorHomeAttentionBoardProjection $attentionBoardProjection,
    ): View {
        return view('operations.display.partials.board', [
            'display' => ShopDisplayBoardProjection::resolve(
                $repairOrderQuery,
                $workboardTriage,
                $totalsCalculator,
                $homeCardSurfaces,
                $attentionBoardProjection,
            ),
        ]);
    }
}
