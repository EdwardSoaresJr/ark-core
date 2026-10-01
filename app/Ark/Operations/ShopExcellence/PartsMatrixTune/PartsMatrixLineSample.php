<?php

namespace App\Ark\Operations\ShopExcellence\PartsMatrixTune;

final readonly class PartsMatrixLineSample
{
    public function __construct(
        public int $lineId,
        public int $costCents,
        public int $sellCents,
        public ?string $pricingMatrixKey,
        public string $pricingMode,
        public bool $sellOverridden,
    ) {}

    public function grossProfitCents(): int
    {
        return $this->sellCents - $this->costCents;
    }
}
