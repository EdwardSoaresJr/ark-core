<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeHotCard
{
    public function __construct(
        public int $repairOrderId,
        public string $vehicleLabel,
        public string $columnKey,
        public string $columnLabel,
        public string $actionStatement,
        public string $totalLabel,
        public int $totalCents,
        public int $urgencyScore,
        public string $urgencyTier,
        public string $href,
        public bool $isRecommended,
    ) {}
}
