<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;

final class RetreatRepairOrderAfterAuthorizationRevocationAction
{
    /** @var list<RepairOrderStatus> */
    private const RETREAT_FROM = [
        RepairOrderStatus::Approved,
        RepairOrderStatus::WaitingParts,
        RepairOrderStatus::ReadyForWork,
        RepairOrderStatus::InProgress,
        RepairOrderStatus::QualityCheck,
    ];

    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        ?User $actor = null,
        string $reason = 'authorization_revoked',
    ): void {
        $repairOrder->refresh()->loadMissing(['concerns', 'lines.concern', 'approvalEvents.revocation']);

        if ($this->totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
            return;
        }

        if (! $repairOrder->status->isOneOf(self::RETREAT_FROM)) {
            return;
        }

        $fromStatus = $repairOrder->status;

        $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval->value]);
        $this->documents->markDirtyForRepairOrder($repairOrder);

        $this->events->record(
            OperationalEventName::RepairOrderLifecycleChanged,
            $repairOrder->fresh(),
            actor: $actor,
            payload: [
                'from_status' => $fromStatus->value,
                'to_status' => RepairOrderStatus::WaitingApproval->value,
                'reason' => $reason,
            ],
        );
    }
}
