<?php

namespace App\Ark\Operations\RepairOrders;

use Brick\Money\Money;
use Illuminate\Support\Collection;

class EstimateTotals
{
    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @param  array<int, int>  $concernSubtotals
     */
    public function __construct(
        public readonly Collection $lines,
        private readonly array $concernSubtotals,
        private readonly int $laborCents,
        private readonly int $partsCents,
        private readonly int $feesCents,
        private readonly int $taxCents,
        private readonly int $taxableSellCents = 0,
        private readonly int $allocatedShopFeesCents = 0,
        private readonly int $standingDiscountCents = 0,
        private readonly int $grossLaborCents = 0,
        private readonly int $grossPartsCents = 0,
        private readonly int $packageCents = 0,
        private readonly int $grossPackageCents = 0,
    ) {}

    public function laborCents(): int
    {
        return $this->laborCents;
    }

    public function partsCents(): int
    {
        return $this->partsCents;
    }

    public function packageCents(): int
    {
        return $this->packageCents;
    }

    public function feesCents(): int
    {
        return $this->feesCents;
    }

    public function taxCents(): int
    {
        return $this->taxCents;
    }

    public function taxableSellCents(): int
    {
        return $this->taxableSellCents;
    }

    public function allocatedShopFeesCents(): int
    {
        return $this->allocatedShopFeesCents;
    }

    public function standingDiscountCents(): int
    {
        return $this->standingDiscountCents;
    }

    public function grossLaborCents(): int
    {
        return $this->grossLaborCents;
    }

    public function grossPartsCents(): int
    {
        return $this->grossPartsCents;
    }

    public function grossPackageCents(): int
    {
        return $this->grossPackageCents;
    }

    public function subtotalBeforeTaxCents(): int
    {
        return max(0, $this->grossLaborCents() + $this->grossPartsCents() + $this->grossPackageCents() + $this->feesCents() - $this->standingDiscountCents());
    }

    public function totalCents(): int
    {
        return $this->subtotalBeforeTaxCents() + $this->taxCents();
    }

    public function concernSubtotalCents(int $concernId): int
    {
        return $this->concernSubtotals[$concernId] ?? 0;
    }

    public function format(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')
            ->getAmount()
            ->toScale(2)
            ->__toString();
    }

    public function decimal(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')
            ->getAmount()
            ->toScale(2)
            ->__toString();
    }
}
