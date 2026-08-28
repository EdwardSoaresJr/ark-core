<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportTotals;
use Illuminate\Database\Eloquent\Builder;

/**
 * Operational cash-flow pipeline for Today — explainable dollars from RO authority.
 */
final class TodayPipelineProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return list<TodayPipelineMetric>
     */
    public function metrics(): array
    {
        $collectedCents = OperationalReportTotals::cashCollectedCents(...TodayPipelineInventoryQuery::currentMonthRange());
        [$awaitingApprovalCents, $awaitingApprovalCount] = $this->sumForStatuses(
            [RepairOrderStatus::WaitingApproval->value],
            estimateTotals: true,
        );
        [$approvedNotStartedCents, $approvedNotStartedCount] = $this->sumForStatuses(
            self::approvedNotStartedSlugs(),
            estimateTotals: false,
        );
        [$readyPickupCents, $readyPickupCount] = $this->sumForStatuses(
            self::readyForPickupSlugs(),
            estimateTotals: false,
        );

        $revenueInFlightCents = $awaitingApprovalCents + $approvedNotStartedCents + $readyPickupCents;
        $revenueInFlightCount = $awaitingApprovalCount + $approvedNotStartedCount + $readyPickupCount;

        return [
            new TodayPipelineMetric(
                key: TodayPipelineInventoryQuery::COLLECTED_THIS_MONTH,
                label: 'Collected This Month',
                amountCents: $collectedCents,
                amountLabel: $this->moneyLabel($collectedCents),
                hint: 'Payments + deposits recorded this month',
                inventoryUrl: TodayPipelineInventoryQuery::url(TodayPipelineInventoryQuery::COLLECTED_THIS_MONTH),
                repairOrderCount: $this->collectedRepairOrderCount(),
            ),
            new TodayPipelineMetric(
                key: TodayPipelineInventoryQuery::AWAITING_APPROVAL,
                label: 'Awaiting Approval',
                amountCents: $awaitingApprovalCents,
                amountLabel: $this->moneyLabel($awaitingApprovalCents),
                hint: 'Estimate totals on ROs waiting on customer approval',
                inventoryUrl: TodayPipelineInventoryQuery::url(TodayPipelineInventoryQuery::AWAITING_APPROVAL),
                repairOrderCount: $awaitingApprovalCount,
            ),
            new TodayPipelineMetric(
                key: TodayPipelineInventoryQuery::APPROVED_NOT_STARTED,
                label: 'Approved Not Started',
                amountCents: $approvedNotStartedCents,
                amountLabel: $this->moneyLabel($approvedNotStartedCents),
                hint: 'Approved work not yet in active production',
                inventoryUrl: TodayPipelineInventoryQuery::url(TodayPipelineInventoryQuery::APPROVED_NOT_STARTED),
                repairOrderCount: $approvedNotStartedCount,
            ),
            new TodayPipelineMetric(
                key: TodayPipelineInventoryQuery::READY_FOR_PICKUP,
                label: 'Ready For Pickup',
                amountCents: $readyPickupCents,
                amountLabel: $this->moneyLabel($readyPickupCents),
                hint: 'Completed work awaiting pickup or final payment',
                inventoryUrl: TodayPipelineInventoryQuery::url(TodayPipelineInventoryQuery::READY_FOR_PICKUP),
                repairOrderCount: $readyPickupCount,
            ),
            new TodayPipelineMetric(
                key: TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT,
                label: 'Revenue In Flight',
                amountCents: $revenueInFlightCents,
                amountLabel: $this->moneyLabel($revenueInFlightCents),
                hint: 'Awaiting approval + approved not started + ready for pickup',
                inventoryUrl: TodayPipelineInventoryQuery::url(TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT),
                repairOrderCount: $revenueInFlightCount,
                emphasized: true,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public static function approvedNotStartedSlugs(): array
    {
        return [
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::WaitingParts->value,
            RepairOrderStatus::ReadyForWork->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function readyForPickupSlugs(): array
    {
        return [
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::Invoiced->value,
            RepairOrderStatus::ReadyPickup->value,
        ];
    }

    /**
     * @param  list<string>  $statusSlugs
     * @return array{0: int, 1: int}
     */
    private function sumForStatuses(array $statusSlugs, bool $estimateTotals): array
    {
        $repairOrders = RepairOrder::query()
            ->with(['lines.concern'])
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->whereIn('status', $statusSlugs)
            ->get();

        $totalCents = 0;

        foreach ($repairOrders as $repairOrder) {
            // GET-safe reads only — never totalsForApprovedWork (persists recalculation).
            $totalCents += $estimateTotals
                ? $this->totalsCalculator->totalsFor($repairOrder)->totalCents()
                : $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
        }

        return [$totalCents, $repairOrders->count()];
    }

    private function collectedRepairOrderCount(): int
    {
        return RepairOrder::query()
            ->tap(fn (Builder $query) => TodayPipelineInventoryQuery::apply(
                $query,
                TodayPipelineInventoryQuery::COLLECTED_THIS_MONTH,
            ))
            ->count();
    }

    private function moneyLabel(int $cents): string
    {
        return '$'.number_format($cents / 100, 0);
    }
}
