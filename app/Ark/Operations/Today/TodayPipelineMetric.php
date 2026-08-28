<?php

namespace App\Ark\Operations\Today;

final readonly class TodayPipelineMetric
{
    public function __construct(
        public string $key,
        public string $label,
        public int $amountCents,
        public string $amountLabel,
        public string $hint,
        public string $inventoryUrl,
        public int $repairOrderCount,
        public bool $emphasized = false,
    ) {}
}
