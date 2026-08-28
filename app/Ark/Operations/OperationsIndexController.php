<?php

namespace App\Ark\Operations;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardCardProjection;
use App\Ark\Operations\Workboard\WorkboardLens;
use App\Ark\Operations\Workboard\WorkboardViewContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperationsIndexController
{
    public function __invoke(
        Request $request,
        EstimateTotalsCalculator $totalsCalculator,
        BalanceDueCalculator $balanceDueCalculator,
    ): View|RedirectResponse {
        $workboardView = WorkboardViewContext::fromRequest($request);
        $workboardLens = $workboardView->lens;

        if ($workboardLens === WorkboardLens::ADVISOR) {
            return redirect()->route('operations.index', $request->query());
        }

        $queueStatuses = WorkboardLens::queueStatusValues($workboardLens);

        $repairOrders = RepairOrder::query()
            ->with([
                'customer',
                'vehicle',
                'assignedTechnician:id,name',
                'communicationEvents:id,repair_order_id,event_type,channel,direction,summary,occurred_at,created_at',
                'lines.concern:id,disposition',
            ])
            ->whereIn('status', $queueStatuses)
            ->latest()
            ->get();

        $repairOrders = WorkboardLens::filterRepairOrders($repairOrders, $workboardLens, $workboardView->filterUser);

        $repairOrdersByStatus = $repairOrders->groupBy(
            fn (RepairOrder $repairOrder): string => $repairOrder->workboardLaneStatus()->value,
        );
        $repairOrderTotals = $repairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $totalsCalculator->totalsFor($repairOrder),
        ]);
        $workboardCards = WorkboardCardProjection::mapForRepairOrders(
            $repairOrders,
            $repairOrderTotals,
            $balanceDueCalculator,
        );

        return view('operations.index', [
            'pressureBands' => WorkboardLens::pressureBands($workboardLens),
            'queueStatuses' => RepairOrderStatus::operationalQueue(),
            'repairOrdersByStatus' => $repairOrdersByStatus,
            'repairOrderTotals' => $repairOrderTotals,
            'workboardCards' => $workboardCards,
            'workboardLens' => $workboardLens,
            'workboardView' => $workboardView,
        ]);
    }
}
