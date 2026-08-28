<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Models\User;

/**
 * Authoritative concern-disposition write. The estimate decision (approve /
 * decline / defer / recommend) is identical whether it comes from the desktop
 * worksheet or the phone — so both surfaces call this one path: update
 * disposition, reset production status, record the operational event, bump the
 * estimate version, recalculate totals, and retreat the lifecycle when nothing
 * is approved anymore.
 */
final class UpdateConcernDispositionAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly RetreatRepairOrderAfterAuthorizationRevocationAction $retreatLifecycle,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcernDisposition $newDisposition,
        ?User $actor,
    ): void {
        $priorDisposition = $concern->disposition->value;

        $concern->update([
            'disposition' => $newDisposition,
            // Keep bay progress across Draft/Recommended/Approved. Reset only when
            // the scope leaves the production path (Deferred / Declined).
            'production_status' => $newDisposition->tracksProductionPath()
                ? $concern->production_status
                : ScopeProductionStatus::Pending,
        ]);
        $this->documents->markDirtyForRepairOrder($repairOrder);

        $concern->refresh();
        $this->events->record(
            OperationalEventName::ConcernDispositionChanged,
            $repairOrder,
            actor: $actor,
            payload: [
                'concern_id' => $concern->id,
                'prior_disposition' => $priorDisposition,
                'new_disposition' => $concern->disposition->value,
            ],
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);
        $this->totalsCalculator->recalculateRepairOrder($repairOrder->fresh());
        $this->retreatLifecycle->execute(
            $repairOrder->fresh(['concerns', 'lines.concern']),
            $actor,
            'no_approved_work_after_disposition_change',
        );

        // Approving/removing scope after Final Invoice is the human gate to revise
        // billed total — keep balance due aligned without a second Refresh click.
        $this->refreshInvoice->executeIfNeeded(
            $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
            $actor,
        );

        $repairOrder->refresh();
    }
}
