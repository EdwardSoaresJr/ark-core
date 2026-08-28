<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RepairOrderLifecycleTransition
{
    public function __construct(
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderCloseoutAuthority $closeout,
        private readonly SoloShopOperations $soloShop,
        private readonly RepairOrderStatusCatalog $statusCatalog,
    ) {}

    public function blockingReason(
        RepairOrder $repairOrder,
        RepairOrderStatus|RepairOrderWorkflowStatus|string $toStatus,
        ?User $actor = null,
        ?string $closeVariantKey = null,
        ?RepairOrderLifecycleSelectCache $selectCache = null,
    ): ?string {
        $toStatusSlug = RepairOrderWorkflowStatus::from($toStatus)->value;
        $roleNames = $selectCache?->actorRoleNames();

        if (! $this->statusCatalog->canTransitionSlug($repairOrder->status->value, $toStatusSlug, $actor, $closeVariantKey, $roleNames)) {
            return 'That lifecycle move is not available from the current repair order state.';
        }

        $lacksEstimateLines = $selectCache !== null
            ? $selectCache->lacksEstimateLines()
            : $this->lacksEstimateLines($repairOrder);

        if (
            $this->requiresEstimateLines($toStatusSlug)
            && $lacksEstimateLines
            && ! (
                $toStatusSlug === RepairOrderStatus::Closed->value
                && $this->statusCatalog->closeVariantBypassesRules($closeVariantKey)
            )
        ) {
            return 'Add estimate lines before moving this repair order forward.';
        }

        if ($toStatusSlug === RepairOrderStatus::Closed->value) {
            if ($closeVariantKey === null && $this->statusCatalog->requiresCloseVariant($toStatusSlug)) {
                return 'Choose how this repair order closed — Paid or Lost — before continuing.';
            }

            if ($selectCache !== null && ! $this->statusCatalog->closeVariantBypassesRules($closeVariantKey)) {
                return $selectCache->standardCloseBlockingReason();
            }

            return $this->closeout->blockingReason($repairOrder, $closeVariantKey);
        }

        $requiresTechnicianAssignment = $selectCache !== null
            ? $selectCache->requiresTechnicianAssignment()
            : $this->soloShop->requiresTechnicianAssignment();

        // RO assigned_technician_id is transitional — Repair Action owners are the
        // production ownership authority. Either satisfies the In Progress gate.
        if (
            $toStatusSlug === RepairOrderStatus::InProgress->value
            && $requiresTechnicianAssignment
            && $repairOrder->assigned_technician_id === null
            && ! $repairOrder->hasRepairActionOwner()
        ) {
            return 'Assign a Repair Action owner before starting work.';
        }

        $hasUnresolvedApprovedParts = $selectCache !== null
            ? $selectCache->hasUnresolvedApprovedParts()
            : $repairOrder->hasUnresolvedApprovedParts();

        // Parts pressure only blocks Ready for Work, and only for Approved scopes.
        // Draft / Recommended / Deferred parts must never block In Progress.
        if (
            $toStatusSlug === RepairOrderStatus::ReadyForWork->value
            && $hasUnresolvedApprovedParts
        ) {
            $detail = $selectCache !== null
                ? $selectCache->partsBlockerSummary()
                : $repairOrder->partsBlockerSummary();

            return $detail !== null
                ? "Approved parts are not ready yet ({$detail}). Receive or install parts before moving to {$this->statusCatalog->labelForSlug($toStatusSlug)}, or set status to Waiting Parts."
                : 'Approved parts are not ready yet. Receive or install parts before moving forward, or set status to Waiting Parts.';
        }

        return null;
    }

    public function move(
        RepairOrder $repairOrder,
        RepairOrderStatus|RepairOrderWorkflowStatus|string $toStatus,
        ?User $actor = null,
        ?string $closeVariantKey = null,
        ?RepairOrderLostReason $lostReason = null,
        ?string $lostReasonNote = null,
        ?bool $reviewRequestSent = null,
        ?string $reviewNotRequestedReason = null,
    ): RepairOrder {
        $repairOrder->ensureOpenForEditing();

        $toStatusSlug = RepairOrderWorkflowStatus::from($toStatus)->value;
        $fromStatus = $repairOrder->status->value;

        abort_if(
            ($reason = $this->blockingReason($repairOrder, $toStatusSlug, $actor, $closeVariantKey)) !== null,
            422,
            $reason,
        );

        $operationalDates = app(RepairOrderOperationalDates::class);

        $attributes = ['status' => $toStatusSlug];

        if ($toStatusSlug === RepairOrderStatus::Closed->value) {
            $attributes['close_variant_key'] = $closeVariantKey;

            if ($closeVariantKey === 'lost') {
                $attributes['lost_reason_key'] = $lostReason?->value;
                $attributes['lost_reason_note'] = $lostReasonNote;
                $attributes['lost_reason_recorded_at'] = now();
                $attributes['lost_reason_recorded_by'] = $actor?->id;
            }

            if ($closeVariantKey === 'paid' && $reviewRequestSent !== null) {
                $attributes['review_request_sent'] = $reviewRequestSent;
                $attributes['review_not_requested_reason'] = $reviewRequestSent ? null : $reviewNotRequestedReason;
                $attributes['review_request_recorded_at'] = now();
                $attributes['review_request_recorded_by'] = $actor?->id;
            }
        }

        $repairOrder->update($attributes);

        if ($toStatusSlug === RepairOrderStatus::ReadyPickup->value || $this->statusCatalog->isTerminalSlug($toStatusSlug)) {
            $operationalDates->applyClose($repairOrder->fresh());
        }

        if ($toStatusSlug === RepairOrderStatus::ReadyPickup->value) {
            $this->issueFinalInvoiceIfEligible($repairOrder->fresh(), $actor);
        }

        if ($this->statusCatalog->isTerminalSlug($toStatusSlug)) {
            $this->documents->snapshotFinalPdfForRepairOrder($repairOrder->fresh(), $actor);
        } else {
            $this->documents->markDirtyForRepairOrder($repairOrder);
        }

        if ($toStatusSlug === RepairOrderStatus::Closed->value && $closeVariantKey === 'paid') {
            app(RepairOrderPosting::class)->postWhenReady($repairOrder->fresh(), $actor);
        }

        $this->events->record(
            OperationalEventName::RepairOrderLifecycleChanged,
            $repairOrder,
            actor: $actor,
            payload: [
                'from_status' => $fromStatus,
                'to_status' => $toStatusSlug,
                'close_variant_key' => $closeVariantKey,
                'lost_reason_key' => $lostReason?->value,
                'review_request_sent' => $reviewRequestSent,
            ],
        );

        return $repairOrder->refresh();
    }

    private function requiresEstimateLines(string $statusSlug): bool
    {
        return ! in_array($statusSlug, [
            RepairOrderStatus::Draft->value,
            RepairOrderStatus::Estimate->value,
        ], true);
    }

    private function lacksEstimateLines(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->relationLoaded('lines')) {
            return $repairOrder->lines->isEmpty();
        }

        return $repairOrder->lines()->doesntExist();
    }

    private function issueFinalInvoiceIfEligible(RepairOrder $repairOrder, ?User $actor): void
    {
        try {
            app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder, $actor);
        } catch (RuntimeException $exception) {
            Log::info('Final invoice was not auto-issued during ready pickup transition.', [
                'repair_order_id' => $repairOrder->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
