<?php

namespace App\Ark\Operations\Financial;

final readonly class RepairOrderDefaultDepositResult
{
    /**
     * @param  list<RepairOrderDefaultDepositLine>  $lines
     */
    public function __construct(
        public bool $enabled,
        public int $totalCents,
        public int $partsCents,
        public int $diagnosticsCents,
        public array $lines = [],
    ) {}

    public function hasAmount(): bool
    {
        return $this->totalCents > 0;
    }

    public function hasLineBreakdown(): bool
    {
        return $this->lines !== [];
    }
}
