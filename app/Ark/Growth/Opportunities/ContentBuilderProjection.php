<?php

namespace App\Ark\Growth\Opportunities;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Models\GrowthOpportunity;

final class ContentBuilderProjection
{
    public function __construct(
        private readonly OpportunityAcceptanceEvaluator $acceptance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(GrowthOpportunity $opportunity): array
    {
        $criteria = $this->acceptance->evaluate(
            $opportunity,
            OpportunityAcceptanceCriteriaTemplate::hydrate(
                $opportunity->action_type,
                $opportunity->acceptance_criteria,
            ),
        );

        $draft = ContentBuilderSchema::normalize(
            $opportunity->content_draft,
            $opportunity->title,
            $opportunity->search_query,
        );

        $previewPath = ContentBuilderSchema::previewPath($draft);
        $previewUrl = $previewPath !== null
            ? route('growth.opportunities.preview', $opportunity)
            : null;

        return [
            'opportunity' => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'action' => $opportunity->action_type->label(),
                'status' => $opportunity->status->label(),
                'status_value' => $opportunity->status->value,
                'search_query' => $opportunity->search_query,
                'landing_path' => $opportunity->landing_path,
                'published_at' => $opportunity->published_at?->format('M j, Y'),
                'validated_at' => $opportunity->validated_at?->format('M j, Y'),
                'is_validated' => $opportunity->status === GrowthOpportunityStatus::Validated,
            ],
            'draft' => $draft,
            'sections' => ContentBuilderSchema::sections(),
            'incomplete_required' => ContentBuilderSchema::incompleteRequiredKeys($draft),
            'acceptance_criteria' => $criteria,
            'acceptance_progress' => $this->acceptance->progressForGate($criteria, 'publish'),
            'measuring_progress' => $this->acceptance->progressForGate($criteria, 'measuring'),
            'can_publish' => $this->acceptance->allRequiredSatisfied($criteria),
            'preview_path' => $previewPath,
            'preview_url' => $previewUrl,
            'report_card' => $this->reportCard($opportunity),
            'transitions' => array_map(
                static fn (GrowthOpportunityStatus $status): string => $status->value,
                $opportunity->status->allowedTransitions(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportCard(GrowthOpportunity $opportunity): ?array
    {
        if (! in_array($opportunity->status, [GrowthOpportunityStatus::Measuring, GrowthOpportunityStatus::Validated], true)) {
            return null;
        }

        $measurement = is_array($opportunity->measurement) ? $opportunity->measurement : [];

        if ($measurement === []) {
            return [
                'status' => $opportunity->status->label(),
                'published_at' => $opportunity->published_at?->format('M j, Y'),
                'measuring_since' => $opportunity->measuring_since?->format('M j, Y'),
                'awaiting_data' => true,
            ];
        }

        return [
            'status' => $opportunity->status->label(),
            'published_at' => $opportunity->published_at?->format('M j, Y'),
            'period_days' => (int) ($measurement['period_days'] ?? 60),
            'baseline' => (array) ($measurement['baseline'] ?? []),
            'current' => (array) ($measurement['current'] ?? []),
            'delta' => (array) ($measurement['delta'] ?? []),
            'roi_validated' => $opportunity->status === GrowthOpportunityStatus::Validated,
            'awaiting_data' => false,
        ];
    }
}
