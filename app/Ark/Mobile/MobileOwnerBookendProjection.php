<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Attention\AdvisorNudgeWeeklyInsightProjection;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\ShopExcellence\OwnerOperationalPulse;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Illuminate\Support\Carbon;

final class MobileOwnerBookendProjection
{
    public function __construct(
        private readonly OwnerOperationalPulse $pulse,
        private readonly AdvisorNudgeWeeklyInsightProjection $nudgeInsight,
    ) {}

    /**
     * Owner day review for mobile — posted sales truth and live queue pressure.
     * No web URLs; mobile renders the projection only.
     */
    public function forDate(?Carbon $shopDate = null): array
    {
        $dateString = $shopDate !== null
            ? OperationalReportDateScope::shopDateString($shopDate)
            : OperationalReportDateScope::shopNow()->toDateString();

        [$from, $to] = OperationalReportDateScope::resolveRange($dateString, $dateString);
        $eod = EndOfDayReportProjection::resolve($from, $to);
        $nudge = $this->nudgeInsight->lastSevenDays();

        return [
            'range_label' => $eod->rangeLabel,
            'shop_date' => $eod->fromDate,
            'min_date' => OperationalReportDateScope::shopDateString(
                OperationalReportDateScope::trustworthyDataStartsAt(),
            ),
            'reconciliation' => [
                'reconciles' => $eod->reconciliation['reconciles'],
                'sales_posted' => $eod->reconciliation['sales_posted'],
                'cash_collected' => $eod->reconciliation['cash_collected'],
                'delta_label' => $eod->reconciliation['delta_label'],
            ],
            'sales_effectiveness' => $this->metrics($eod->salesEffectiveness),
            'shop_metrics' => $this->metrics($eod->shopMetrics),
            'priorities' => $this->pulse->dayReviewPriorities(),
            'target_review' => [
                'stale' => ShopExcellenceTargets::targetReviewStale(),
                'last_review' => ShopExcellenceTargets::lastTargetReview(),
            ],
            'nudge_insight' => (($nudge['acted'] ?? 0) + ($nudge['dismissed'] ?? 0)) > 0
                ? [
                    'acted' => (int) ($nudge['acted'] ?? 0),
                    'dismissed' => (int) ($nudge['dismissed'] ?? 0),
                    'action_rate' => $nudge['action_rate'],
                ]
                : null,
            'poll_after_seconds' => 120,
        ];
    }

    /**
     * @param  list<array{label: string, value: string, hint?: string|null}>  $rows
     * @return list<array{label: string, value: string, hint: string|null}>
     */
    private function metrics(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
                'hint' => filled($row['hint'] ?? null) ? (string) $row['hint'] : null,
            ])
            ->values()
            ->all();
    }
}
