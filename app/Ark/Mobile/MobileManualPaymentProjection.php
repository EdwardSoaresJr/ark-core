<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Manual payment capture on mobile — cash, check, external card.
 * Same ledger path as desktop Record Payment; amount is server-validated.
 */
final class MobileManualPaymentProjection
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

        if (! $balance->hasIssuedInvoice || $balance->balanceDueCents <= 0) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);

        return [
            'can_record' => true,
            'balance_due_cents' => $balance->balanceDueCents,
            'balance_due_label' => $totals->format($balance->balanceDueCents),
            'balance_due_decimal' => number_format($balance->balanceDueCents / 100, 2, '.', ''),
            'methods' => [
                ['value' => PaymentMethod::Cash->value, 'label' => PaymentMethod::Cash->label()],
                ['value' => PaymentMethod::Card->value, 'label' => PaymentMethod::Card->label()],
                ['value' => PaymentMethod::Check->value, 'label' => PaymentMethod::Check->label()],
            ],
        ];
    }
}
