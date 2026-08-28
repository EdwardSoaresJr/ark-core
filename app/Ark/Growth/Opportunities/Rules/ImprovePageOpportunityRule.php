<?php

namespace App\Ark\Growth\Opportunities\Rules;

use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Integrations\SearchMetricsAggregator;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityEffort;
use App\Ark\Growth\Opportunities\OpportunityCandidate;
use App\Ark\Growth\Opportunities\OpportunityEstimatedLift;
use Illuminate\Support\Str;

final class ImprovePageOpportunityRule implements OpportunityRule
{
    public function __construct(
        private readonly SearchMetricsAggregator $metrics,
        private readonly ContentRegistry $content,
    ) {}

    public function candidates(): array
    {
        $minImpressions = (int) config('growth.opportunities.improve_min_impressions', 50);
        $ctrCeiling = (float) config('growth.opportunities.improve_ctr_ceiling', 0.02);
        $items = [];

        foreach ($this->metrics->aggregatedLandingPages() as $row) {
            $path = (string) $row->path;
            $impressions = (int) $row->impressions;
            $ctr = (float) $row->ctr;
            $position = (float) ($row->position ?? 99);

            if ($impressions < $minImpressions) {
                continue;
            }

            $content = $this->content->findByPath($path);
            $lowCtr = $ctr < $ctrCeiling;
            $strikingDistance = $position >= 4 && $position <= 12;

            if (! $lowCtr && ! $strikingDistance) {
                continue;
            }

            $recommendation = $lowCtr ? 'Rewrite title and meta description' : 'Add FAQ and internal links';
            $effort = $lowCtr ? GrowthOpportunityEffort::Small : GrowthOpportunityEffort::Medium;
            $monthlyImpressions = $this->monthlyEstimate($impressions);
            $potentialCtr = min(0.08, $ctr + 0.015);
            $potentialClicks = (int) round($monthlyImpressions * $potentialCtr);
            $currentClicks = (int) round($monthlyImpressions * $ctr);
            $priority = (int) min(999, round($monthlyImpressions / 8 + (12 - min(11, $position)) * 20));

            $pageLabel = $content?->title ?? basename($path);

            $items[] = new OpportunityCandidate(
                key: 'improve:'.Str::slug(trim($path, '/') ?: 'home'),
                action: GrowthOpportunityAction::Improve,
                title: 'Improve: '.$pageLabel,
                impactSummary: sprintf(
                    'CTR %.1f%% · position %.1f · %s',
                    $ctr * 100,
                    $position,
                    $recommendation,
                ),
                effort: $effort,
                priorityScore: $priority,
                estimatedLift: new OpportunityEstimatedLift(
                    currentLabel: $currentClicks.' clicks/month',
                    potentialLabel: $potentialClicks.' clicks/month',
                    priorityLabel: $priority >= 600 ? 'High' : 'Medium',
                    steps: [
                        ['label' => 'Current', 'value' => sprintf('%.1f%% CTR · %.1f position', $ctr * 100, $position)],
                        ['label' => 'Potential', 'value' => sprintf('~%.1f%% CTR · %d clicks/month', $potentialCtr * 100, $potentialClicks)],
                        ['label' => 'Expected priority', 'value' => $priority >= 600 ? 'High' : 'Medium'],
                    ],
                ),
                evidenceFacts: [
                    ['label' => 'Landing page', 'value' => $path],
                    ['label' => '28-day impressions', 'value' => number_format($impressions)],
                    ['label' => 'CTR', 'value' => number_format($ctr * 100, 1).'%'],
                    ['label' => 'Avg position', 'value' => number_format($position, 1)],
                    ['label' => 'Recommendation', 'value' => $recommendation],
                ],
                evidenceSignals: [
                    ['label' => 'Impressions above threshold', 'satisfied' => true],
                    ['label' => 'CTR or position shows upside', 'satisfied' => $lowCtr || $strikingDistance],
                ],
                landingPath: $path,
                growthContentId: $content?->id,
            );
        }

        return $items;
    }

    private function monthlyEstimate(int $lookbackImpressions): int
    {
        $days = max(1, app(SearchMetricsAggregator::class)->lookbackDays());

        return (int) round(($lookbackImpressions / $days) * 30);
    }
}
