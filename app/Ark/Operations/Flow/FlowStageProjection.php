<?php

namespace App\Ark\Operations\Flow;

final readonly class FlowStageProjection
{
    public function __construct(
        public FlowStageKey $stageKey,
        public string $label,
        public int $count,
        public int $oldestAgeMinutes,
        public string $oldestAgeLabel,
        public int $medianAgeMinutes,
        public string $medianAgeLabel,
        public int $revenueCents,
        public string $revenueLabel,
        public int $pressureScore,
        public string $inventoryUrl,
        public ?string $signalSummary = null,
    ) {}
}
