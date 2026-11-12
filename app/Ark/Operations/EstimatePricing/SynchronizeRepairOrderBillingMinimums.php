<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Facades\Session;

/**
 * Recalculates billing-class minimum Fee lines and flashes advisor notices.
 */
final class SynchronizeRepairOrderBillingMinimums
{
    public const SESSION_NOTICES = 'billing_minimum_notices';

    public function __construct(
        private readonly ApplyBillingClassMinimumsAction $apply,
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * @return list<array{posture: string, label: string, from_hours: string, to_hours: string}>
     */
    public function execute(RepairOrder $repairOrder): array
    {
        $notices = $this->apply->synchronize($repairOrder->fresh(['concerns.lines', 'lines']));

        if ($notices !== []) {
            Session::flash(self::SESSION_NOTICES, $notices);
        }

        $this->calculator->recalculateRepairOrder($repairOrder->fresh(['concerns', 'lines']));

        return $notices;
    }
}
