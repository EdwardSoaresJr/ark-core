<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Manual refund capture on mobile — same ledger path as desktop Record Refund.
 */
final class MobileManualRefundProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function control(RepairOrder $repairOrder, User $viewer, string $profile): ?array
    {
        if ($profile === 'technician'
            || ! $viewer->can(ArkCapability::RepairOrdersManage->value)) {
            return null;
        }

        $balance = $repairOrder->balanceDue();

        if ($repairOrder->isTerminal()
            || ! $balance->hasIssuedInvoice
            || $balance->paymentsAppliedCents <= 0) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);

        return [
            'can_record' => true,
            'payments_applied_cents' => $balance->paymentsAppliedCents,
            'payments_applied_label' => $totals->format($balance->paymentsAppliedCents),
            'balance_due_cents' => $balance->balanceDueCents,
            'balance_due_label' => $totals->format($balance->balanceDueCents),
        ];
    }
}
