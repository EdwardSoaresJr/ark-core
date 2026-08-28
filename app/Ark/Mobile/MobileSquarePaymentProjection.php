<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Payments\SquareConfiguration;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Square terminal capture on mobile — server-authoritative amount, terminal only.
 * Keyed card entry stays on desktop (Web Payments SDK).
 */
final class MobileSquarePaymentProjection
{
    public function __construct(
        private readonly SquareConfiguration $square,
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

        $terminalEnabled = $this->square->terminalEnabled();

        if (! $terminalEnabled) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);

        return [
            'can_charge_terminal' => true,
            'balance_due_cents' => $balance->balanceDueCents,
            'balance_due_label' => $totals->format($balance->balanceDueCents),
            'balance_due_decimal' => number_format($balance->balanceDueCents / 100, 2, '.', ''),
            'terminal_enabled' => true,
        ];
    }
}
