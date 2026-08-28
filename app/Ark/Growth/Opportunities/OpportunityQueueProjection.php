<?php

namespace App\Ark\Growth\Opportunities;

use App\Ark\Growth\Models\GrowthOpportunity;

final class OpportunityQueueProjection
{
    public function __construct(
        private readonly OpportunityQueueRepository $repository,
        private readonly OpportunityAcceptanceEvaluator $acceptance,
    ) {}

    /**
     * @return array{
     *     opportunities: list<array<string, mixed>>,
     *     in_flight: list<array<string, mixed>>,
     *     posture: array<string, int>,
     *     meta: array<string, mixed>
     * }
     */
    public function resolve(int $limit = 5): array
    {
        $this->repository->syncDiscovered();

        return [
            'opportunities' => array_map(
                fn (GrowthOpportunity $opportunity): array => $this->present($opportunity),
                $this->repository->topOpportunities($limit),
            ),
            'in_flight' => array_map(
                fn (GrowthOpportunity $opportunity): array => $this->present($opportunity),
                $this->repository->inFlight(),
            ),
            'posture' => $this->repository->postureCounts(),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'lookback_days' => (int) config('growth.opportunities.lookback_days', 28),
                'success_criteria' => 'Top '.$limit.' highest-value SEO tasks with evidence',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GrowthOpportunity $opportunity): array
    {
        $criteria = $this->acceptance->evaluate(
            $opportunity,
            OpportunityAcceptanceCriteriaTemplate::hydrate(
                $opportunity->action_type,
                $opportunity->acceptance_criteria,
            ),
        );

        return [
            'id' => $opportunity->id,
            'key' => $opportunity->key,
            'action' => $opportunity->action_type->label(),
            'action_type' => $opportunity->action_type->value,
            'title' => $opportunity->title,
            'impact' => $opportunity->impact_summary,
            'effort' => $opportunity->effort->label(),
            'effort_value' => $opportunity->effort->value,
            'status' => $opportunity->status->label(),
            'status_value' => $opportunity->status->value,
            'is_validated' => $opportunity->status === GrowthOpportunityStatus::Validated,
            'priority_score' => $opportunity->priority_score,
            'estimated_lift' => $opportunity->estimated_lift,
            'evidence' => $opportunity->evidence,
            'acceptance_criteria' => $criteria,
            'acceptance_progress' => $this->acceptance->progressForGate($criteria, 'publish'),
            'can_publish' => $this->acceptance->allRequiredSatisfied($criteria),
            'start_url' => route('growth.opportunities.start', $opportunity),
            'search_query' => $opportunity->search_query,
            'landing_path' => $opportunity->landing_path,
            'measurement' => $opportunity->measurement,
            'report_card' => $this->reportCardSnippet($opportunity),
            'published_at' => $opportunity->published_at?->format('M j, Y'),
            'validated_at' => $opportunity->validated_at?->format('M j, Y'),
            'build_url' => route('growth.opportunities.build', $opportunity),
            'transitions' => array_map(
                static fn (GrowthOpportunityStatus $status): string => $status->value,
                $opportunity->status->allowedTransitions(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportCardSnippet(GrowthOpportunity $opportunity): ?array
    {
        if (! in_array($opportunity->status, [GrowthOpportunityStatus::Measuring, GrowthOpportunityStatus::Validated], true)) {
            return null;
        }

        $measurement = is_array($opportunity->measurement) ? $opportunity->measurement : [];
        $delta = (array) ($measurement['delta'] ?? []);

        if ($delta === []) {
            return [
                'awaiting_data' => true,
                'status' => $opportunity->status->label(),
            ];
        }

        return [
            'awaiting_data' => false,
            'status' => $opportunity->status->label(),
            'impressions' => (int) ($delta['impressions'] ?? 0),
            'clicks' => (int) ($delta['clicks'] ?? 0),
            'leads' => (int) ($delta['leads'] ?? 0),
            'repair_orders' => (int) ($delta['repair_orders'] ?? 0),
            'revenue_cents' => (int) ($delta['revenue_cents'] ?? 0),
            'roi_validated' => $opportunity->status === GrowthOpportunityStatus::Validated,
        ];
    }
}
