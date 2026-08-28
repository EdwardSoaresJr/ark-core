<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\AdvisorQueuePressure;
use App\Ark\Operations\Reports\ShopBehaviorPulse;
use App\Ark\Operations\Staff\StaffFrontDoorLandingSummary;
use Brick\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OperationalReportController
{
    public function __invoke(Request $request): View
    {
        $trustworthyStartsAt = OperationalReportDateScope::trustworthyDataStartsAt();
        [$from, $to] = OperationalReportDateScope::resolveRange(
            $request->input('from'),
            $request->input('to'),
        );

        $rangeMetrics = new OperationalReportRangeMetrics($from, $to);
        $intelligence = new OperationalIntelligence($from, $to);

        $activeRepairOrders = RepairOrder::query()
            ->with([
                'assignedTechnician:id,name',
                'concerns:id,repair_order_id,disposition',
                'lines:id,repair_order_id,repair_order_concern_id,type,quantity,unit_price_cents,part_cost_cents,subtotal_cents,tax_cents,shop_fee_cents,total_cents,procurement_state',
            ])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->tap(fn ($query) => OperationalReportDateScope::applyTrustworthyDataFloor($query))
            ->get();

        $activeTotalsByRepairOrder = OperationalReportTotals::totalCentsByRepairOrderId($activeRepairOrders->pluck('id'));
        $activeLaborCentsByRepairOrder = OperationalReportTotals::laborCentsByRepairOrderId($activeRepairOrders->pluck('id'));

        $activeTab = OperationalReportTab::resolve($request->input('tab'));
        $paymentReconciliation = $activeTab === OperationalReportTab::Financial
            ? (new OperationalReportPaymentReconciliation($from, $to))->summary()
            : null;

        return view('operations.reports.operational', [
            'from' => $from,
            'to' => $to,
            'activeTab' => $activeTab,
            'trustworthyStartsAt' => $trustworthyStartsAt,
            'kpis' => $rangeMetrics->kpis(),
            'pressureRows' => $this->pressureRows($activeRepairOrders, $activeTotalsByRepairOrder),
            'financialRows' => $rangeMetrics->financialRows(),
            'paymentReconciliation' => $paymentReconciliation,
            'advisorRows' => $rangeMetrics->advisorRows(),
            'technicianRows' => $rangeMetrics->technicianRows($activeRepairOrders, $activeLaborCentsByRepairOrder),
            'opportunityRows' => $rangeMetrics->opportunityRows(),
            'recentClosures' => $rangeMetrics->recentClosures(),
            'marginHealthRows' => $rangeMetrics->marginHealthRows(),
            'breakEvenSummary' => $rangeMetrics->breakEvenSummary(),
            'ownerPlSummary' => $rangeMetrics->ownerPlSummary(),
            'operationalTruth' => $intelligence->operationalTruth(),
            'approvalMomentum' => $intelligence->approvalMomentum(),
            'liability' => $intelligence->liability(),
            'recommendationConversion' => $intelligence->recommendationConversion(),
            'diagnosticRepairFollowThrough' => $intelligence->diagnosticRepairFollowThrough(),
            'advisorQueuePressure' => app(AdvisorQueuePressure::class)->rows(),
            'shopBehaviorPulse' => app(ShopBehaviorPulse::class)->priorities(),
            'frontDoorLanding' => app(StaffFrontDoorLandingSummary::class)->lastDays(),
        ]);
    }

    /**
     * @param  Collection<int, RepairOrder>  $activeRepairOrders
     * @param  Collection<int, int>  $totalsByRepairOrder
     * @return list<array{pressure: string, count: int, value: string, action: string}>
     */
    private function pressureRows(Collection $activeRepairOrders, Collection $totalsByRepairOrder): array
    {
        $waitingApproval = $activeRepairOrders->where('status', RepairOrderStatus::WaitingApproval);
        $waitingParts = $activeRepairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->workboardLaneStatus()->is(RepairOrderStatus::WaitingParts),
        );
        $unpaidPickups = $activeRepairOrders->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid());
        $readyExecution = $activeRepairOrders
            ->whereIn('status', [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork])
            ->reject(fn (RepairOrder $repairOrder): bool => $repairOrder->hasUnresolvedApprovedParts());
        $stalled = $activeRepairOrders->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->updated_at->lt(now()->subHours(4)));

        return [
            [
                'pressure' => 'Approvals aging',
                'count' => $waitingApproval->count(),
                'value' => $this->money(OperationalReportTotals::sumTotalCentsFor($waitingApproval, $totalsByRepairOrder)),
                'action' => 'Follow up customer authorization',
            ],
            [
                'pressure' => 'Parts backlog',
                'count' => $waitingParts->count(),
                'value' => $waitingParts->map(fn (RepairOrder $repairOrder): ?string => $repairOrder->partsBlockerSummary())->filter()->join(' · ') ?: 'No blocker detail',
                'action' => 'Clear procurement blockers',
            ],
            [
                'pressure' => 'Ready execution',
                'count' => $readyExecution->count(),
                'value' => $readyExecution->whereNull('assigned_technician_id')->count().' unassigned',
                'action' => 'Assign and dispatch work',
            ],
            [
                'pressure' => 'Unpaid pickup',
                'count' => $unpaidPickups->count(),
                'value' => $this->money(OperationalReportTotals::sumTotalCentsFor($unpaidPickups, $totalsByRepairOrder)),
                'action' => 'Collect before release',
            ],
            [
                'pressure' => 'Stalled ROs',
                'count' => $stalled->count(),
                'value' => 'No movement in 4h',
                'action' => 'Review owner and next step',
            ],
        ];
    }

    private function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
