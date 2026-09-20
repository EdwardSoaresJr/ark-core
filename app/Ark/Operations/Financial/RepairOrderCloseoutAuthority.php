<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;

final class RepairOrderCloseoutAuthority
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderStatusCatalog $statusCatalog,
    ) {}

    public function canClose(RepairOrder $repairOrder, ?string $closeVariantKey = null): bool
    {
        return $this->blockingReason($repairOrder, $closeVariantKey) === null;
    }

    public function blockingReason(
        RepairOrder $repairOrder,
        ?string $closeVariantKey = null,
        ?BalanceDueResult $balance = null,
        ?EstimateDocument $invoice = null,
    ): ?string {
        if ($this->statusCatalog->closeVariantBypassesRules($closeVariantKey)) {
            return null;
        }

        if (! $repairOrder->status->isOneOf([
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::Completed,
        ])) {
            return 'Repair order must be ready for pickup or invoiced before paid closeout.';
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
            return 'Generate the final invoice before closing this repair order.';
        }

        if ($balance->balanceDueCents > 0) {
            return 'Collect the full balance due before closing this repair order.';
        }

        return null;
    }
}
