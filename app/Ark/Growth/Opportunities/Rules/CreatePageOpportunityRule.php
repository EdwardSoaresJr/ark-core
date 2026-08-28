<?php

namespace App\Ark\Growth\Opportunities\Rules;

use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Integrations\SearchMetricsAggregator;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityEffort;
use App\Ark\Growth\Opportunities\OpportunityCandidate;
use App\Ark\Growth\Opportunities\OpportunityEstimatedLift;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use Illuminate\Support\Str;

final class CreatePageOpportunityRule implements OpportunityRule
{
    public function __construct(
        private readonly SearchMetricsAggregator $metrics,
        private readonly ContentRegistry $content,
    ) {}

    public function candidates(): array
    {
        $minImpressions = (int) config('growth.opportunities.create_min_impressions', 100);
        $items = [];

        foreach ($this->metrics->aggregatedQueries() as $row) {
            $query = trim((string) $row->query);
            $impressions = (int) $row->impressions;

            if ($query === '' || $impressions < $minImpressions) {
                continue;
            }

            if ($this->hasMatchingContent($query)) {
                continue;
            }

            $title = $this->humanTitle($query);
            $monthlyImpressions = $this->monthlyEstimate($impressions);
            $priority = $this->priorityScore($monthlyImpressions, (float) ($row->position ?? 50));

            $items[] = new OpportunityCandidate(
                key: 'create:'.Str::slug($query),
                action: GrowthOpportunityAction::Create,
                title: 'Create: '.$title,
                impactSummary: sprintf(
                    '%s monthly searches · no matching page exists',
                    number_format($monthlyImpressions),
                ),
                effort: $monthlyImpressions >= 1500 ? GrowthOpportunityEffort::Large : GrowthOpportunityEffort::Medium,
                priorityScore: $priority,
                estimatedLift: new OpportunityEstimatedLift(
                    currentLabel: '0 impressions',
                    potentialLabel: number_format($monthlyImpressions).' impressions/month',
                    priorityLabel: $priority >= 700 ? 'High' : ($priority >= 400 ? 'Medium' : 'Normal'),
                    steps: [
                        ['label' => 'Current', 'value' => '0 impressions'],
                        ['label' => 'Potential', 'value' => number_format($monthlyImpressions).' impressions/month'],
                        ['label' => 'Expected priority', 'value' => $priority >= 700 ? 'High' : 'Medium'],
                    ],
                ),
                evidenceFacts: [
                    ['label' => 'Search query', 'value' => $query],
                    ['label' => '28-day impressions', 'value' => number_format($impressions)],
                    ['label' => 'Avg position', 'value' => $row->position !== null ? number_format((float) $row->position, 1) : '—'],
                    ['label' => 'Matching page', 'value' => 'None'],
                ],
                evidenceSignals: [
                    ['label' => 'Search demand above threshold', 'satisfied' => true],
                    ['label' => 'No GrowthContent path match', 'satisfied' => true],
                ],
                searchQuery: $query,
                recommendedPath: '/common-problems/'.Str::slug($query),
            );
        }

        return $items;
    }

    private function hasMatchingContent(string $query): bool
    {
        $slug = Str::slug($query);

        if ($this->content->findBySlug($slug) !== null) {
            return true;
        }

        foreach (CommonProblemRegistry::all() as $problem) {
            if (str_contains((string) $problem['slug'], $slug) || str_contains($slug, (string) $problem['slug'])) {
                return true;
            }

            if (str_contains(strtolower((string) $problem['title']), strtolower($query))) {
                return true;
            }
        }

        $path = '/common-problems/'.$slug;

        return $this->content->findByPath($path) !== null;
    }

    private function humanTitle(string $query): string
    {
        return Str::title($query);
    }

    private function monthlyEstimate(int $lookbackImpressions): int
    {
        $days = max(1, app(SearchMetricsAggregator::class)->lookbackDays());

        return (int) round(($lookbackImpressions / $days) * 30);
    }

    private function priorityScore(int $monthlyImpressions, float $position): int
    {
        $positionFactor = max(1, 20 - min(19, $position));

        return (int) min(999, round(($monthlyImpressions / 10) + ($positionFactor * 15)));
    }
}
