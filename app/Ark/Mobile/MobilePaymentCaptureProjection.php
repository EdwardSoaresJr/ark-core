<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class MobilePaymentCaptureProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
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

        $balance = $repairOrder->balanceDue();

        if (! $balance->hasIssuedInvoice || $balance->balanceDueCents <= 0) {
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
            'balance_due_cents' => $balance->balanceDueCents,
            'balance_due_label' => $totals->format($balance->balanceDueCents),
            'balance_due_decimal' => number_format($balance->balanceDueCents / 100, 2, '.', ''),
            'devices' => $devices,
            'default_device_ref' => $devices[0]['device_ref'],
        ];
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
