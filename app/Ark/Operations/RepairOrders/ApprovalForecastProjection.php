<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;

/**
 * Approval Forecast - conversation prep, not invoice authority.
 *
 * Projects: Approved (what the customer authorized, at the approved amounts) · Needs Authorization
 * (added or raised work on approved scopes) · Needs Approval (pending recommendations) · If All
 * Approved. Disposable. Rebuild from EstimateTotalsCalculator and WorkCompletionAuthorization read
 * APIs. No new store.
 *
 * Presentation differs by audience (staff compact vs customer invoice story).
 */
final class ApprovalForecastProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    /**
     * @return array{
     *     visible: bool,
     *     approved_cents: int,
     *     approved_label: string,
     *     needs_authorization_cents: int,
     *     needs_authorization_label: string,
     *     pending_cents: int,
     *     pending_label: string,
     *     pending_concern_count: int,
     *     projected_cents: int,
     *     projected_label: string,
     *     breakdown_label: string,
     * }
     */
    public function for(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['concerns.lines', 'lines.concern']);

        $approved = $this->calculator->approvedTotalsForRead($repairOrder);
        $pending = $this->calculator->recommendedTotalsForRead($repairOrder);

        $approvedCents = $approved->totalCents();
        $needsAuthorizationCents = $this->authorization->workOutsideApprovedSalesCents($repairOrder);
        $pendingCents = $pending->totalCents();
        $projectedCents = $approvedCents + $needsAuthorizationCents + $pendingCents;

        $pendingConcernCount = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Recommended)
            ->count();

        // Visible when there is work to forecast - recommended work (the friction case and
        // pure-pending builds alike) or work on approved scopes the customer has not authorized.
        $visible = $pendingCents > 0 || $pendingConcernCount > 0 || $needsAuthorizationCents > 0;

        return [
            'visible' => $visible,
            'approved_cents' => $approvedCents,
            'approved_label' => $approved->format($approvedCents),
            'needs_authorization_cents' => $needsAuthorizationCents,
            'needs_authorization_label' => $approved->format($needsAuthorizationCents),
            'pending_cents' => $pendingCents,
            'pending_label' => $pending->format($pendingCents),
            'pending_concern_count' => $pendingConcernCount,
            'projected_cents' => $projectedCents,
            'projected_label' => $approved->format($projectedCents),
            // The totals breakdown sums the approved scopes at their current amounts, so it is only
            // the approved amount when nothing on those scopes awaits authorization.
            'breakdown_label' => $needsAuthorizationCents > 0 ? 'Estimate Total' : 'Approved',
        ];
    }
}
