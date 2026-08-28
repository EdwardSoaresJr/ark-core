<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeColumnStat
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public int $amountCents,
        public string $amountLabel,
    ) {}
}
