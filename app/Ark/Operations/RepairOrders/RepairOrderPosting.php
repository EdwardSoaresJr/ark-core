<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Models\User;

final class RepairOrderPosting
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderOperationalDates $operationalDates,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function blockingReason(
        RepairOrder $repairOrder,
        ?BalanceDueResult $balance = null,
        ?EstimateDocument $invoice = null,
    ): ?string {
        if ($repairOrder->posted_at !== null) {
            return 'This repair order is already posted.';
        }

        if ($balance === null) {
            $projection = $this->balanceDue->projectForRepairOrder($repairOrder);
            $balance = $projection->balance;
            $invoice ??= $projection->invoice;
        } else {
            $invoice ??= $balance->hasIssuedInvoice
                ? $this->balanceDue->issuedInvoice($repairOrder)
                : null;
        }

        if ($invoice === null) {
            return 'Generate the final invoice before posting this repair order.';
        }

        if ($balance->balanceDueCents > 0) {
            return 'Collect the full balance due before posting this repair order.';
        }

        if ($repairOrder->close_variant_key === 'lost') {
            return 'Lost repair orders cannot be posted as sales.';
        }

        return null;
    }

    public function post(RepairOrder $repairOrder, ?User $actor = null): RepairOrder
    {
        abort_if(
            ($reason = $this->blockingReason($repairOrder)) !== null,
            422,
            $reason,
        );

        return $this->applyPost($repairOrder, $actor);
    }

    public function postWhenReady(RepairOrder $repairOrder, ?User $actor = null): RepairOrder
    {
        if (! $repairOrder->readyToPost()) {
            return $repairOrder;
        }

        return $this->applyPost($repairOrder, $actor);
    }

    private function applyPost(RepairOrder $repairOrder, ?User $actor = null): RepairOrder
    {
        $this->operationalDates->applyPost($repairOrder);

        $this->events->record(
            OperationalEventName::RepairOrderPosted,
            $repairOrder->fresh(),
            actor: $actor,
        );

        return $repairOrder->refresh();
    }
}
