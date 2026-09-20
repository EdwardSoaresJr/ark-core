<?php

namespace App\Ark\Operations\RepairOrders;

/** @deprecated Use EstimateCompanionCompletenessProjection — catalog, not a timing-only rule. */
final class TimingJobCompanionFluidProjection
{
    public function __construct(
        private readonly EstimateCompanionCompletenessProjection $companions = new EstimateCompanionCompletenessProjection,
    ) {}

    public function for(RepairOrder $repairOrder): array
    {
        return $this->companions->for($repairOrder);
    }
}
