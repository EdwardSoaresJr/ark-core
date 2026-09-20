<?php

namespace App\Ark\Operations\Approvals;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\AdvanceRepairOrderAfterCustomerAuthorizationAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RetreatRepairOrderAfterAuthorizationRevocationAction;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordCustomerAuthorizationAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly EstimateDocumentService $documents,
        private readonly AdvanceRepairOrderAfterCustomerAuthorizationAction $advanceLifecycle,
        private readonly RetreatRepairOrderAfterAuthorizationRevocationAction $retreatLifecycle,
        private readonly StorePortalApprovalSignatureAction $signatures,
    ) {}

    /**
     * @param  array<int, string>  $concernDispositions  concern_id => disposition value
     */
    public function execute(
        RepairOrder $repairOrder,
        ApprovalType $approvalType,
        ApprovalSource $source,
        string $approvedBy,
        ?int $approvedAmountCents,
        ?string $notes,
        array $concernDispositions,
        ?string $signatureDataUrl = null,
        ?User $actor = null,
    ): ApprovalEvent {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->loadMissing(['concerns', 'customer']);

        $signaturePath = filled($signatureDataUrl)
            ? $this->signatures->storePending($signatureDataUrl)
            : null;

        return DB::transaction(function () use (
            $repairOrder,
            $approvalType,
            $source,
            $approvedBy,
            $approvedAmountCents,
            $notes,
            $concernDispositions,
            $signaturePath,
            $actor,
        ): ApprovalEvent {
            $this->applyConcernDispositions($repairOrder, $concernDispositions);

            $this->totalsCalculator->recalculateRepairOrder($repairOrder);
            $repairOrder->refresh()->load('concerns');

            $resolvedAmountCents = $this->resolveApprovedAmountCents(
                $repairOrder,
                $approvalType,
                $approvedAmountCents,
            );

            $approval = ApprovalEvent::query()->create([
                'visit_id' => $repairOrder->id,
                'estimate_snapshot_reference' => $this->estimateSnapshotReference($repairOrder),
                'approval_type' => $approvalType,
                'approved_amount_cents' => $resolvedAmountCents,
                'source' => $source,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'notes' => $notes,
                'signature_path' => $signaturePath,
            ]);

            $this->documents->markDirtyForRepairOrder($repairOrder);

            $repairOrder->refresh()->load(['concerns', 'lines.concern']);

            $this->advanceLifecycle->execute($repairOrder, $approval, $actor);
            $this->retreatLifecycle->execute(
                $repairOrder->fresh(['concerns', 'lines.concern']),
                $actor,
                'no_approved_work_after_authorization',
            );

            return $approval;
        });
    }

    /**
     * @param  array<int, string>  $concernDispositions
     */
    private function applyConcernDispositions(RepairOrder $repairOrder, array $concernDispositions): void
    {
        foreach ($concernDispositions as $concernId => $disposition) {
            $concern = $repairOrder->concerns->firstWhere('id', (int) $concernId);

            if (! $concern instanceof RepairOrderConcern) {
                continue;
            }

            $nextDisposition = RepairOrderConcernDisposition::tryFrom((string) $disposition);

            if (! $nextDisposition instanceof RepairOrderConcernDisposition) {
                continue;
            }

            if ($concern->disposition === $nextDisposition) {
                continue;
            }

            $concern->update(['disposition' => $nextDisposition]);
        }
    }

    private function resolveApprovedAmountCents(
        RepairOrder $repairOrder,
        ApprovalType $approvalType,
        ?int $approvedAmountCents,
    ): int {
        return match ($approvalType) {
            ApprovalType::Repair, ApprovalType::Partial => $approvedAmountCents
                ?? $this->totalsCalculator->totalsForApprovedWork($repairOrder)->totalCents(),
            ApprovalType::Diagnostic => $approvedAmountCents ?? 0,
        };
    }

    private function estimateSnapshotReference(RepairOrder $repairOrder): ?string
    {
        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->latest('id')
            ->first();

        if (! $document) {
            return null;
        }

        return 'estimate-document-'.$document->id;
    }
}
