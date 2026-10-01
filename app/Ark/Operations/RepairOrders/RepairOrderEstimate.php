<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;

class RepairOrderEstimate
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    public function totalsFor(RepairOrder $repairOrder): EstimateTotals
    {
        return $this->calculator->totalsFor($repairOrder);
    }

    public function lineTotalCents(string|float|int $quantity, int $unitPriceCents): int
    {
        return $this->calculator->lineTotalCents($quantity, $unitPriceCents);
    }

    public function unitPriceCents(string|float|int $unitPrice): int
    {
        return $this->calculator->unitPriceCents($unitPrice);
    }
}
