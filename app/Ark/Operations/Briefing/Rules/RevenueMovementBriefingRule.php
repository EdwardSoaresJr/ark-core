<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;

final class RevenueMovementBriefingRule implements BriefingRule
{
    public function key(): string
    {
        return 'revenue_movement';
    }

    public function items(BriefingContext $context): array
    {
        $yesterdayEod = EndOfDayReportProjection::resolve($context->yesterdayFrom, $context->yesterdayTo);
        $priorEod = EndOfDayReportProjection::resolve($context->priorWeekFrom, $context->priorWeekTo);

        $yesterdayCents = $this->postedCentsFromEod($yesterdayEod);
        $priorAverageCents = max(1, (int) round($this->postedCentsFromEod($priorEod) / 7));

        if ($yesterdayCents === 0) {
            return [];
        }

        $deltaPercent = (int) round((($yesterdayCents - $priorAverageCents) / $priorAverageCents) * 100);
        $spikeThreshold = (int) config('briefing.revenue_spike_threshold_percent', 25);
        $dropThreshold = (int) config('briefing.revenue_drop_threshold_percent', 25);

        if ($deltaPercent >= $spikeThreshold) {
            return [
                $this->movementItem(
                    context: $context,
                    headline: 'Revenue spike yesterday',
                    summary: sprintf(
                        '$%s posted · %d%% above prior-week daily average',
                        number_format($yesterdayCents / 100, 0),
                        $deltaPercent,
                    ),
                    priority: BriefingPriority::Normal,
                    reason: 'Posted sales exceeded prior-week daily average by configured threshold.',
                    facts: [
                        ['label' => 'Yesterday', 'value' => '$'.number_format($yesterdayCents / 100, 0)],
                        ['label' => 'Prior week avg/day', 'value' => '$'.number_format($priorAverageCents / 100, 0)],
                    ],
                    evidence: [
                        new BriefingEvidenceItem(
                            sourceType: 'end_of_day_projection',
                            summary: 'End of day posted sales',
                            occurredAt: $context->yesterdayTo,
                            detail: $yesterdayEod->reconciliation['sales_posted'],
                            sourceLabel: 'Posted sales',
                        ),
                    ],
                    variant: 'spike',
                ),
            ];
        }

        if ($deltaPercent <= -$dropThreshold) {
            return [
                $this->movementItem(
                    context: $context,
                    headline: 'Revenue drop yesterday',
                    summary: sprintf(
                        '$%s posted · %d%% below prior-week daily average',
                        number_format($yesterdayCents / 100, 0),
                        abs($deltaPercent),
                    ),
                    priority: BriefingPriority::High,
                    reason: 'Posted sales fell below prior-week daily average by configured threshold.',
                    facts: [
                        ['label' => 'Yesterday', 'value' => '$'.number_format($yesterdayCents / 100, 0)],
                        ['label' => 'Prior week avg/day', 'value' => '$'.number_format($priorAverageCents / 100, 0)],
                    ],
                    evidence: [
                        new BriefingEvidenceItem(
                            sourceType: 'end_of_day_projection',
                            summary: 'End of day posted sales',
                            occurredAt: $context->yesterdayTo,
                            detail: $yesterdayEod->reconciliation['sales_posted'],
                            sourceLabel: 'Posted sales',
                        ),
                    ],
                    variant: 'drop',
                ),
            ];
        }

        return [];
    }

    /**
     * @param  list<array{label: string, value: string}>  $facts
     * @param  list<BriefingEvidenceItem>  $evidence
     */
    private function movementItem(
        BriefingContext $context,
        string $headline,
        string $summary,
        BriefingPriority $priority,
        string $reason,
        array $facts,
        array $evidence,
        string $variant,
    ): BriefingItem {
        return new BriefingItem(
            key: $this->key().'_'.$variant,
            headline: $headline,
            summary: $summary,
            priority: $priority,
            confidence: new BriefingConfidence(
                score: 90,
                reason: $reason,
                signals: [
                    ['label' => 'Compared to prior-week daily average', 'satisfied' => true],
                    ['label' => 'Uses EndOfDayReportProjection', 'satisfied' => true],
                ],
                facts: $facts,
            ),
            evidenceItems: $evidence,
            actionUrl: route('operations.reports.end-of-day', [
                'date' => OperationalReportDateScope::shopDateString($context->yesterdayFrom),
            ]),
            actionLabel: 'View end of day',
        );
    }

    private function postedCentsFromEod(\App\Ark\Operations\Reports\EndOfDayReportProjection $eod): int
    {
        $raw = str_replace(['$', ','], '', $eod->reconciliation['sales_posted'] ?? '0');

        return (int) round(((float) $raw) * 100);
    }
}
