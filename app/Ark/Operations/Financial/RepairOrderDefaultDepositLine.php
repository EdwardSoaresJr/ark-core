<?php

namespace App\Ark\Operations\Financial;

final readonly class RepairOrderDefaultDepositLine
{
    public function __construct(
        public int $lineId,
        public string $description,
        public string $category,
        public string $categoryLabel,
        public int $sellSubtotalCents,
        public int $taxCents,
        public int $shopFeeCents,
        public int $amountCents,
        public bool $includedByDefault = true,
    ) {}
}
