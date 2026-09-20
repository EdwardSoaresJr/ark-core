<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class MobilePaymentCaptureDepositProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
        private readonly PaymentCaptureReadinessProjection $readiness,
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

        if (! $this->canChargeDeposit($repairOrder)) {
            return null;
        }

        $capture = $this->readiness->current();
        $devices = $this->readyDevices($capture['devices'] ?? []);
        $canCharge = ($capture['ready'] ?? false)
            && ($capture['supports_terminal'] ?? false)
            && $devices !== [];

        if (! $canCharge) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);

        return [
            'can_charge_terminal' => true,
            'default_amount_decimal' => $this->defaultAmountDecimal($repairOrder),
            'suggested_deposit_label' => $this->suggestedLabel($repairOrder, $totals),
            'remaining_suggested_label' => $this->remainingLabel($repairOrder, $totals),
            'devices' => $devices,
            'default_device_ref' => $devices[0]['device_ref'],
        ];
    }

    private function canChargeDeposit(RepairOrder $repairOrder): bool
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

    private function suggestedLabel(RepairOrder $repairOrder, EstimateTotals $totals): ?string
    {
        $defaultDeposit = $this->defaultDepositCalculator->forRepairOrder($repairOrder);

        if (! $defaultDeposit->hasAmount()) {
            return null;
        }

        return $totals->format($defaultDeposit->totalCents);
    }

    private function remainingLabel(RepairOrder $repairOrder, EstimateTotals $totals): ?string
    {
        $remaining = $this->depositGuard->remainingSuggestedDepositCents($repairOrder);

        if ($remaining === null || $remaining <= 0) {
            return null;
        }

        return $totals->format($remaining);
    }

    /**
     * @param  list<array{device_ref: string, label: string, ready: bool}>  $devices
     * @return list<array{device_ref: string, label: string, ready: bool}>
     */
    private function readyDevices(array $devices): array
    {
        return array_values(array_filter(
            $devices,
            static fn (array $device): bool => ($device['ready'] ?? false) === true && filled($device['device_ref'] ?? null),
        ));
    }
}
