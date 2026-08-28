<?php

namespace App\Ark\Growth\Opportunities;

final readonly class OpportunityCandidate
{
    /**
     * @param  list<array{label: string, value: string}>  $evidenceFacts
     * @param  list<array{label: string, satisfied: bool}>  $evidenceSignals
     */
    public function __construct(
        public string $key,
        public GrowthOpportunityAction $action,
        public string $title,
        public string $impactSummary,
        public GrowthOpportunityEffort $effort,
        public int $priorityScore,
        public OpportunityEstimatedLift $estimatedLift,
        public array $evidenceFacts,
        public array $evidenceSignals,
        public ?string $searchQuery = null,
        public ?string $landingPath = null,
        public ?int $growthContentId = null,
        public ?string $recommendedPath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evidencePayload(): array
    {
        return [
            'facts' => $this->evidenceFacts,
            'signals' => $this->evidenceSignals,
        ];
    }
}
