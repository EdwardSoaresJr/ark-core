<?php

namespace App\Ark\Growth\Opportunities;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Content\OpportunityContentDraftSeeder;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\Rules\CreatePageOpportunityRule;
use App\Ark\Growth\Opportunities\Rules\ImprovePageOpportunityRule;
use App\Ark\Growth\Opportunities\Rules\OpportunityRule;

final class OpportunityQueueRepository
{
    /** @var list<OpportunityRule> */
    private array $rules;

    public function __construct(
        CreatePageOpportunityRule $createPage,
        ImprovePageOpportunityRule $improvePage,
        private readonly OpportunityContentDraftSeeder $draftSeeder,
    ) {
        $this->rules = [$createPage, $improvePage];
    }

    public function syncDiscovered(): int
    {
        $candidates = $this->discoverCandidates();
        $synced = 0;

        foreach ($candidates as $candidate) {
            $existing = GrowthOpportunity::query()->where('key', $candidate->key)->first();

            if ($existing !== null && $existing->status !== GrowthOpportunityStatus::Discovered) {
                continue;
            }

            $acceptanceCriteria = OpportunityAcceptanceCriteriaTemplate::hydrate(
                $candidate->action,
                $existing?->acceptance_criteria,
            );

            $contentDraft = $this->resolveContentDraft(
                $candidate->title,
                $candidate->searchQuery,
                $existing?->content_draft,
            );

            GrowthOpportunity::query()->updateOrCreate(
                ['key' => $candidate->key],
                [
                    'action_type' => $candidate->action,
                    'title' => $candidate->title,
                    'impact_summary' => $candidate->impactSummary,
                    'effort' => $candidate->effort,
                    'status' => $existing?->status ?? GrowthOpportunityStatus::Discovered,
                    'priority_score' => $candidate->priorityScore,
                    'estimated_lift' => $candidate->estimatedLift->toArray(),
                    'evidence' => $candidate->evidencePayload(),
                    'acceptance_criteria' => $acceptanceCriteria,
                    'content_draft' => $contentDraft,
                    'growth_content_id' => $candidate->growthContentId,
                    'search_query' => $candidate->searchQuery,
                    'landing_path' => $candidate->landingPath,
                ],
            );
            $synced++;
        }

        return $synced;
    }

    /**
     * @return list<OpportunityCandidate>
     */
    public function discoverCandidates(): array
    {
        $items = [];

        foreach ($this->rules as $rule) {
            $items = array_merge($items, $rule->candidates());
        }

        usort(
            $items,
            static function (OpportunityCandidate $left, OpportunityCandidate $right): int {
                $score = $right->priorityScore <=> $left->priorityScore;
                if ($score !== 0) {
                    return $score;
                }

                return $right->effort->sortWeight() <=> $left->effort->sortWeight();
            },
        );

        return $items;
    }

    /**
     * @return list<GrowthOpportunity>
     */
    public function topOpportunities(int $limit = 5): array
    {
        return GrowthOpportunity::query()
            ->whereIn('status', [
                GrowthOpportunityStatus::Discovered,
                GrowthOpportunityStatus::Accepted,
                GrowthOpportunityStatus::Building,
            ])
            ->orderByDesc('priority_score')
            ->orderByRaw("CASE effort WHEN 'small' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return list<GrowthOpportunity>
     */
    public function inFlight(): array
    {
        return GrowthOpportunity::query()
            ->whereIn('status', [
                GrowthOpportunityStatus::Accepted,
                GrowthOpportunityStatus::Building,
                GrowthOpportunityStatus::Published,
                GrowthOpportunityStatus::Measuring,
            ])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function postureCounts(): array
    {
        $counts = GrowthOpportunity::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return [
            'validated' => (int) ($counts[GrowthOpportunityStatus::Validated->value] ?? 0),
            'measuring' => (int) ($counts[GrowthOpportunityStatus::Measuring->value] ?? 0),
            'building' => (int) ($counts[GrowthOpportunityStatus::Building->value] ?? 0),
            'discovered' => (int) ($counts[GrowthOpportunityStatus::Discovered->value] ?? 0),
            'accepted' => (int) ($counts[GrowthOpportunityStatus::Accepted->value] ?? 0),
            'published' => (int) ($counts[GrowthOpportunityStatus::Published->value] ?? 0),
        ];
    }

    public function ensureDraft(GrowthOpportunity $opportunity): GrowthOpportunity
    {
        $label = ContentBuilderSchema::stripActionPrefix((string) $opportunity->title);
        $normalized = ContentBuilderSchema::normalize(
            $opportunity->content_draft,
            $label,
            $opportunity->search_query,
        );

        if (ContentBuilderSchema::needsSeeding($normalized)) {
            $seeded = $this->draftSeeder->seed($opportunity->search_query, $label);
            $normalized = ContentBuilderSchema::normalize(
                ContentBuilderSchema::mergePreferFilled($seeded, $normalized),
                $label,
                $opportunity->search_query,
            );
        }

        $encoded = json_encode($normalized);
        $stored = json_encode($opportunity->content_draft);

        if ($encoded !== $stored) {
            $opportunity->content_draft = $normalized;
            $opportunity->save();

            return $opportunity->fresh();
        }

        return $opportunity;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContentDraft(?string $title, ?string $searchQuery, ?array $existing): array
    {
        $label = ContentBuilderSchema::stripActionPrefix((string) $title);
        $seeded = $this->draftSeeder->seed($searchQuery, $label);

        return ContentBuilderSchema::normalize(
            ContentBuilderSchema::mergePreferFilled($seeded, $existing),
            $label,
            $searchQuery,
        );
    }
}
