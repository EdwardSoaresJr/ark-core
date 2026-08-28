<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\SquareConfiguration;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Square terminal deposit on mobile — before final invoice, terminal only.
 */
final class MobileSquareDepositProjection
{
    public function __construct(
        private readonly SquareConfiguration $square,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
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

        if (! $this->canChargeDeposit($repairOrder) || ! $this->square->terminalEnabled()) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);
        $defaultDecimal = $this->defaultAmountDecimal($repairOrder);

        return [
            'can_charge_terminal' => true,
            'default_amount_decimal' => $defaultDecimal,
            'suggested_deposit_label' => $this->suggestedLabel($repairOrder, $totals),
            'remaining_suggested_label' => $this->remainingLabel($repairOrder, $totals),
            'terminal_enabled' => true,
        ];
    }

    private function canChargeDeposit(RepairOrder $repairOrder): bool
    {
        $balance = $repairOrder->balanceDue();

        if ($repairOrder->isTerminal() || $balance->hasIssuedInvoice) {
            return false;
        }

        if (! $this->square->operational()) {
            return false;
        }

        return $this->depositGuard->remainingAllowedDepositCents($repairOrder) > 0;
    }

    private function defaultAmountDecimal(RepairOrder $repairOrder): string
    {
        $remaining = $this->depositGuard->remainingSuggestedDepositCents($repairOrder);

        if ($remaining !== null && $remaining > 0) {
            return number_format($remaining / 100, 2, '.', '');
        }

        $collectable = $this->depositGuard->remainingCollectableDepositCents($repairOrder);

        if ($collectable > 0) {
            return number_format($collectable / 100, 2, '.', '');
        }

        $defaultDeposit = $this->defaultDepositCalculator->forRepairOrder($repairOrder);

        if ($defaultDeposit->enabled && $defaultDeposit->hasAmount()) {
            return number_format($defaultDeposit->totalCents / 100, 2, '.', '');
        }

        return '';
    }

    private function suggestedLabel(RepairOrder $repairOrder, \App\Ark\Operations\RepairOrders\EstimateTotals $totals): ?string
    {
        $defaultDeposit = $this->defaultDepositCalculator->forRepairOrder($repairOrder);

        if (! $defaultDeposit->hasAmount()) {
            return null;
        }

        return $totals->format($defaultDeposit->totalCents);
    }

    private function remainingLabel(RepairOrder $repairOrder, \App\Ark\Operations\RepairOrders\EstimateTotals $totals): ?string
    {
        $remaining = $this->depositGuard->remainingSuggestedDepositCents($repairOrder);

        if ($remaining === null || $remaining <= 0) {
            return null;
        }

        return $totals->format($remaining);
    }
}
