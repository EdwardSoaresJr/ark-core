<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class AdvanceRepairOrderAfterCustomerAuthorizationAction
{
    /**
     * A later approval of added or raised work must not move a job that is already
     * past approval back to Approved.
     *
     * @var list<RepairOrderStatus>
     */
    private const PAST_APPROVAL = [
        RepairOrderStatus::WaitingParts,
        RepairOrderStatus::ReadyForWork,
        RepairOrderStatus::InProgress,
        RepairOrderStatus::QualityCheck,
        RepairOrderStatus::Completed,
        RepairOrderStatus::Invoiced,
        RepairOrderStatus::ReadyPickup,
    ];

    public function __construct(
        private readonly RepairOrderLifecycleTransition $lifecycle,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    public function execute(RepairOrder $repairOrder, ApprovalEvent $approval, ?User $actor = null): void
    {
        $repairOrder->refresh()->loadMissing(['concerns', 'lines.concern']);

        if (! $this->totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
            return;
        }

        $repairOrder->refresh();

        if ($repairOrder->isTerminal() || $repairOrder->status->isOneOf(self::PAST_APPROVAL)) {
            return;
        }

        foreach ([RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork] as $target) {
            if ($repairOrder->status === $target) {
                continue;
            }

            $reason = $this->lifecycle->blockingReason($repairOrder, $target, $actor);

            if ($reason !== null) {
                if ($target === RepairOrderStatus::Approved) {
                    Log::info('Customer authorization recorded but repair order could not advance to approved.', [
                        'repair_order_id' => $repairOrder->id,
                        'approval_event_id' => $approval->id,
                        'reason' => $reason,
                    ]);
                }

                break;
            }

            $this->lifecycle->move($repairOrder, $target, $actor);
            $repairOrder->refresh();
        }
    }
}
