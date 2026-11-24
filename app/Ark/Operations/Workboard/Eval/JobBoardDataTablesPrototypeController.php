<?php

namespace App\Ark\Operations\Workboard\Eval;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Operations\Workboard\JobBoardListPageBuilder;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Isolated review prototype: DataTables.js list chrome + shared Job Board builder/projection.
 * Does not replace operations.index and does not install Yajra.
 */
final class JobBoardDataTablesPrototypeController
{
    public function page(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        EstimateTotalsCalculator $totalsCalculator,
        AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        JobBoardListPageBuilder $listPageBuilder,
    ): View {
        JobBoardDataTablesPrototypeGate::abortUnlessEnabled();

        $layoutPreference = JobBoardLayoutPreference::resolve($request);
        $repairOrders = $repairOrderQuery->forAdvisor();

        // Initial chrome + cards share the builder; list body loads via AJAX.
        $listPage = $listPageBuilder->build($request, $repairOrders);
        $pageRepairOrders = new \Illuminate\Database\Eloquent\Collection($listPage->pageRepairOrders->all());
        $repairOrderTotals = $pageRepairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
        ]);

        return view('operations.workboard.eval.datatables-prototype', [
            'cockpit' => AdvisorHomeCockpitProjection::forJobBoard(
                $listPage->pageColumns,
                $repairOrderTotals,
                $repairOrders->count(),
            ),
            'homeBoardTechnicians' => $homeCardSurfaces->technicianOptions($repairOrders),
            'jobBoardListPage' => $listPage,
            'jobBoardListRows' => $listPage->rows,
            'jobBoardLaneTabs' => $listPage->laneTabs,
            'jobBoardLayoutMode' => $layoutPreference['mode'] === 'auto'
                ? JobBoardLayout::List->value
                : $layoutPreference['mode'],
            'jobBoardLayoutExplicit' => true,
            'prototypeDataUrl' => route('operations.eval.job-board-datatables.data'),
            'prototypePageUrl' => route('operations.eval.job-board-datatables'),
            'productionListUrl' => route('operations.index', ['view' => 'list']),
        ]);
    }

    public function data(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        JobBoardListPageBuilder $listPageBuilder,
    ): JsonResponse {
        JobBoardDataTablesPrototypeGate::abortUnlessEnabled();

        $mapped = JobBoardDataTablesPayload::mapRequest($request);
        $merged = $request->duplicate(array_merge($request->query(), [
            'q' => $mapped['q'] !== '' ? $mapped['q'] : $request->query('q'),
            'sort' => $mapped['sort'],
            'dir' => $mapped['dir'],
            'page' => $mapped['page'],
            'length' => $mapped['per_page'],
            'lane' => $request->query('lane'),
            'tech' => $request->query('tech'),
        ]));
        $merged->attributes->set('job_board.allow_variable_page_size', true);

        $repairOrders = $repairOrderQuery->forAdvisor();
        $listPage = $listPageBuilder->build($merged, $repairOrders);

        return response()->json(JobBoardDataTablesPayload::fromPage($listPage, $mapped['draw']));
    }
}
