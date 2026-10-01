<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RetreatRepairOrderAfterAuthorizationRevocationAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RevokeCustomerAuthorizationAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly EstimateDocumentService $documents,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RetreatRepairOrderAfterAuthorizationRevocationAction $retreatLifecycle,
    ) {}

    /**
     * @param  list<int>  $concernIds
     */
    public function execute(
        RepairOrder $repairOrder,
        ApprovalEvent $approvalEvent,
        ApprovalSource $source,
        string $revokedBy,
        ?string $notes,
        array $concernIds,
        RepairOrderConcernDisposition $revertDisposition,
        ?User $actor = null,
    ): ApprovalRevocationEvent {
        $repairOrder->ensureOpenForEditing();

        abort_unless($approvalEvent->visit_id === $repairOrder->id, 404);

        if ($this->balanceDue->issuedInvoice($repairOrder) !== null) {
            throw ValidationException::withMessages([
                'authorization' => 'Authorization cannot be revoked after the final invoice has been issued.',
            ]);
        }

        if ($approvalEvent->revocation()->exists()) {
            throw ValidationException::withMessages([
                'authorization' => 'This authorization has already been revoked.',
            ]);
        }

        $repairOrder->loadMissing('concerns');

        $concernIds = collect($concernIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($concernIds === []) {
            throw ValidationException::withMessages([
                'concern_ids' => 'Select at least one approved scope to revoke.',
            ]);
        }

        $concerns = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => in_array($concern->id, $concernIds, true));

        if ($concerns->count() !== count($concernIds)) {
            throw ValidationException::withMessages([
                'concern_ids' => 'One or more selected scopes could not be found on this repair order.',
            ]);
        }

        foreach ($concerns as $concern) {
            if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
                throw ValidationException::withMessages([
                    'concern_ids' => 'Only approved scopes can be revoked.',
                ]);
            }
        }

        if (! in_array($revertDisposition, [RepairOrderConcernDisposition::Recommended, RepairOrderConcernDisposition::Deferred], true)) {
            throw ValidationException::withMessages([
                'revert_disposition' => 'Revoked scopes must return to recommended or deferred.',
            ]);
        }

        return DB::transaction(function () use (
            $repairOrder,
            $approvalEvent,
            $source,
            $revokedBy,
            $notes,
            $concernIds,
            $revertDisposition,
            $actor,
            $concerns,
        ): ApprovalRevocationEvent {
            foreach ($concerns as $concern) {
                $concern->update(['disposition' => $revertDisposition]);
            }

            $revocation = ApprovalRevocationEvent::query()->create([
                'visit_id' => $repairOrder->id,
                'approval_event_id' => $approvalEvent->id,
                'source' => $source,
                'revoked_by' => $revokedBy,
                'revoked_at' => now(),
                'notes' => $notes,
                'recorded_by_user_id' => $actor?->id,
                'reverted_concern_ids' => $concernIds,
            ]);

            $this->totalsCalculator->recalculateRepairOrder($repairOrder->fresh());
            $this->documents->markDirtyForRepairOrder($repairOrder);
            $this->retreatLifecycle->execute($repairOrder->fresh(), $actor);

            return $revocation;
        });
    }
}
