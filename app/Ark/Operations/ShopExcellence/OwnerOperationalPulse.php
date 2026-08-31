<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportPaymentReconciliation;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\ShopBehaviorPulse;
use Illuminate\Support\Carbon;

final class OwnerOperationalPulse
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function todayRange(): array
    {
        $today = OperationalReportDateScope::shopNow()->toDateString();

        return OperationalReportDateScope::resolveRange($today, $today);
    }

    /**
     * Daily digest — closed sales + operational pressure for the shop day.
     *
     * @return array{
     *     range_label: string,
     *     headlines: list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>,
     *     priorities: list<array{label: string, count: int, hint: string, tone: string}>,
     *     reconciliation: array{
     *         reconciles: bool,
     *         sales_posted: string,
     *         cash_collected: string,
     *         reconciled: string,
     *         delta_label: string
     *     },
     *     lines: list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>,
     *     report_url: string,
     *     financial_url: string,
     *     margin_health_url: string,
     *     owner_pl_url: string,
     *     day_review_url: string,
     *     bookend_url: string
     * }
     */
    public function dailyDigest(?Carbon $from = null, ?Carbon $to = null): array
    {
        [$from, $to] = $from !== null && $to !== null
            ? [$from, $to]
            : $this->todayRange();

        $reconciliation = (new OperationalReportPaymentReconciliation($from, $to))->summary();
        $eod = EndOfDayReportProjection::resolve($from, $to);
        $headlineLabels = [
            'Total ROs',
            'Effective Labor Rate',
            'Avg RO (Sales)',
            'Gross Profit',
        ];

        $fromLabel = OperationalReportDateScope::shopDateString($from);
        $toLabel = OperationalReportDateScope::shopDateString($to);

        $reconciliationRows = collect($reconciliation['rows']);

        return [
            'range_label' => OperationalReportDateScope::shopRangeLabel($from, $to),
            'headlines' => array_merge(
                [
                    [
                        'label' => 'Sales Posted',
                        'value' => $eod->reconciliation['sales_posted'],
                        'hint' => 'Posted invoice total',
                        'tone' => null,
                    ],
                    [
                        'label' => 'Cash Collected',
                        'value' => $eod->reconciliation['cash_collected'],
                        'hint' => $eod->reconciliation['reconciles'] ? 'Reconciles with posted sales' : 'Delta '.$eod->reconciliation['delta_label'],
                        'tone' => $eod->reconciliation['reconciles'] ? 'good' : 'warn',
                    ],
                ],
                collect($eod->salesEffectiveness)
                    ->merge($eod->shopMetrics)
                    ->filter(fn (array $line): bool => in_array($line['label'], $headlineLabels, true))
                    ->map(fn (array $line): array => [
                        'label' => $line['label'],
                        'value' => $line['value'],
                        'hint' => $line['hint'] ?? '',
                        'tone' => null,
                    ])
                    ->values()
                    ->all(),
            ),
            'priorities' => collect($this->dayReviewPriorities())
                ->filter(fn (array $priority): bool => $priority['count'] > 0)
                ->values()
                ->all(),
            'reconciliation' => [
                'reconciles' => $reconciliation['reconciles'],
                'sales_posted' => $reconciliation['posted_ro_summary']['total'],
                'cash_collected' => (string) $reconciliationRows->firstWhere('key', 'total_cashiered')['amount'],
                'reconciled' => (string) $reconciliationRows->firstWhere('key', 'reconciled')['amount'],
                'delta_label' => $reconciliation['delta_label'],
            ],
            'lines' => [],
            'report_url' => route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
            ]),
            'financial_url' => route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
                'tab' => 'financial',
            ]),
            'margin_health_url' => route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
                'tab' => 'margin-health',
            ]),
            'owner_pl_url' => route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
                'tab' => 'owner-pl',
            ]),
            'day_review_url' => route('operations.owner.day-review'),
            'bookend_url' => route('operations.owner.day-review'),
        ];
    }

    /**
     * Queue pressure for tomorrow — open approvals, parts, unpaid pickup, etc.
     *
     * @return list<array{label: string, count: int, hint: string, tone: string}>
     */
    public function dayReviewPriorities(): array
    {
        return app(ShopBehaviorPulse::class)->priorities();
    }

    /**
     * @deprecated Use dayReviewPriorities()
     *
     * @return list<array{label: string, count: int, hint: string, tone: string}>
     */
    public function bookendPriorities(): array
    {
        return $this->dayReviewPriorities();
    }
}
