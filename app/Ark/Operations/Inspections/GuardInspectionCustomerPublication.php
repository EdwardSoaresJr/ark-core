<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use RuntimeException;

/**
 * Enforces customer-publication readiness at share/send boundaries.
 * Staff preview tokens are not gated here. Existing portal tokens stay resolvable.
 */
final class GuardInspectionCustomerPublication
{
    public function __construct(
        private readonly InspectionReviewReadinessProjection $readiness,
    ) {}

    public function assertReady(RepairOrder $repairOrder): void
    {
        $reason = $this->blockReason($repairOrder);
        if ($reason === null) {
            return;
        }

        throw new RuntimeException($reason);
    }

    public function abortUnlessReady(RepairOrder $repairOrder, int $status = 422): void
    {
        $reason = $this->blockReason($repairOrder);
        if ($reason === null) {
            return;
        }

        abort($status, $reason);
    }

    public function blockReason(RepairOrder $repairOrder): ?string
    {
        if (InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) === 0) {
            return 'Record at least one inspection finding before sharing.';
        }

        $state = $this->readiness->forRepairOrder($repairOrder);

        if ($state['ready_for_customer_publication']) {
            return null;
        }

        if (! $state['coverage_complete']) {
            // Publication readiness is independent of lifecycle Optional/Required.
            // Do not say "required" — that conflates customer-share completeness with closeout policy.
            return 'Inspection checklist coverage is incomplete. Finish the checklist before sharing with the customer.';
        }

        if ($state['publication_blockers'] !== []) {
            return 'Inspection is not ready for customer publication: '
                .implode('; ', $state['publication_blockers']);
        }

        return 'Inspection is not ready for customer publication.';
    }
}
