<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Manual deposit capture on mobile — cash, check, external card before invoice.
 */
final class MobileManualDepositProjection
{
    public function __construct(
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

        if (! $this->canRecordManualDeposit($repairOrder)) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);
        $defaultAmount = $this->defaultAmountDecimal($repairOrder);

        return [
            'can_record' => true,
            'suggested_deposit_label' => $this->suggestedLabel($repairOrder, $totals),
            'default_amount_decimal' => $defaultAmount,
            'remaining_suggested_label' => $this->remainingLabel($repairOrder, $totals),
            'methods' => [
                ['value' => PaymentMethod::Cash->value, 'label' => PaymentMethod::Cash->label()],
                ['value' => PaymentMethod::Card->value, 'label' => PaymentMethod::Card->label()],
                ['value' => PaymentMethod::Check->value, 'label' => PaymentMethod::Check->label()],
            ],
        ];
    }

    private function canRecordManualDeposit(RepairOrder $repairOrder): bool
    {
        $balance = $repairOrder->balanceDue();

        if ($repairOrder->isTerminal() || $balance->hasIssuedInvoice) {
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
