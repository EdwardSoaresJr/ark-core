<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;

/**
 * Approval Forecast — conversation prep, not invoice authority.
 *
 * Projects: Approved · Needs Approval (pending recommendations) · If All Approved.
 * Disposable. Rebuild from EstimateTotalsCalculator read APIs. No new store.
 *
 * Presentation differs by audience (staff compact vs customer invoice story).
 */
final class ApprovalForecastProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     visible: bool,
     *     approved_cents: int,
     *     approved_label: string,
     *     pending_cents: int,
     *     pending_label: string,
     *     pending_concern_count: int,
     *     projected_cents: int,
     *     projected_label: string,
     * }
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['concerns.lines', 'lines.concern']);

        $approved = $this->calculator->approvedTotalsForRead($repairOrder);
        $pending = $this->calculator->recommendedTotalsForRead($repairOrder);

        $approvedCents = $approved->totalCents();
        $pendingCents = $pending->totalCents();
        $projectedCents = $approvedCents + $pendingCents;

        $pendingConcernCount = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Recommended)
            ->count();

        // Visible when there is recommended work to forecast — the friction case
        // (approved diagnostic + recommendations) and pure-pending builds alike.
        $visible = $pendingCents > 0 || $pendingConcernCount > 0;

        return [
            'visible' => $visible,
            'approved_cents' => $approvedCents,
            'approved_label' => $approved->format($approvedCents),
            'pending_cents' => $pendingCents,
            'pending_label' => $pending->format($pendingCents),
            'pending_concern_count' => $pendingConcernCount,
            'projected_cents' => $projectedCents,
            'projected_label' => $approved->format($projectedCents),
        ];
    }
}
