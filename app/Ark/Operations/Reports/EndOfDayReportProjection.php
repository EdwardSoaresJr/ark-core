<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tekmetric-style End of Day card — one authoritative answer for posted sales truth.
 */
final readonly class EndOfDayReportProjection
{
    /**
     * @param  list<array{label: string, value: string, hint: string|null}>  $salesEffectiveness
     * @param  list<array{label: string, value: string}>  $shopMetrics
     * @param  list<array{label: string, value: string, tone: 'subtract'|'total'|null}>  $roSummary
     * @param  list<array{category: string, non_taxable: string, taxable: string, net: string}>  $salesBreakdown
     * @param  array{
     *     reconciles: bool,
     *     sales_posted: string,
     *     write_offs: string,
     *     cash_collected: string,
     *     delta_label: string
     * }  $reconciliation
     */
    public function __construct(
        public string $rangeLabel,
        public string $fromDate,
        public string $toDate,
        public array $salesEffectiveness,
        public array $shopMetrics,
        public array $roSummary,
        public array $salesBreakdown,
        public array $reconciliation,
        public string $reportUrl,
        public string $financialUrl,
    ) {}

    public static function resolve(Carbon $from, Carbon $to): self
    {
        $metrics = new OperationalReportRangeMetrics($from, $to);
        $reconciliation = (new OperationalReportPaymentReconciliation($from, $to))->summary();
        $targets = ShopExcellenceTargets::current();

        $components = OperationalReportTotals::postedSalesComponents($from, $to);
        $postedCount = self::postedCount($from, $to);
        $hoursSold = self::postedLaborHours($from, $to);
        $hoursPresented = self::presentedLaborHours($from, $to);
        $salesCents = $metrics->postedInvoiceSalesCents();
        $costsComplete = $metrics->postedCostsAreMatched();
        $grossProfitCents = $metrics->postedGrossProfitCents();
        $grossMarginPercent = $grossProfitCents !== null
            ? ReportingStandardsV1::grossMarginPercent($salesCents, $salesCents - $grossProfitCents)
            : null;

        $effectiveLaborRateCents = $hoursSold > 0 ? (int) round($components['labor_cents'] / $hoursSold) : null;
        $closeRatioPercent = $hoursPresented > 0
            ? (int) round(($hoursSold / $hoursPresented) * 100)
            : null;
        $aroCents = ReportingStandardsV1::aroCents($salesCents, $postedCount);
        $avgRoProfitCents = $grossProfitCents !== null && $postedCount > 0 ? (int) round($grossProfitCents / $postedCount) : null;
        $salesPerHourCents = $hoursSold > 0 ? (int) round($salesCents / $hoursSold) : null;
        $grossProfitPerHourCents = $grossProfitCents !== null && $hoursSold > 0 ? (int) round($grossProfitCents / $hoursSold) : null;

        $fromLabel = OperationalReportDateScope::shopDateString($from);
        $toLabel = OperationalReportDateScope::shopDateString($to);
        $reconciliationRows = collect($reconciliation['rows']);

        return new self(
            rangeLabel: OperationalReportDateScope::shopRangeLabel($from, $to),
            fromDate: $fromLabel,
            toDate: $toLabel,
            salesEffectiveness: [
                self::metric('Car count', (string) $postedCount, 'Posted repair orders'),
                self::metric('Hours presented', number_format($hoursPresented, 2).' total', 'Labor hours presented on posted repair orders, including work still waiting'),
                self::metric('Hours sold', number_format($hoursSold, 2).' total', 'Billed hours on approved labor'),
                self::metric(
                    'Closing ratio (hours)',
                    ReportingStandardsV1::percentOrIncomplete($hoursPresented > 0 || $hoursSold <= 0, $closeRatioPercent),
                    'Billed hours ÷ hours presented. Pending work is included.',
                ),
                self::metric(
                    'Effective labor rate',
                    $components['labor_cents'] > 0 && $hoursSold <= 0
                        ? ReportingStandardsV1::INCOMPLETE_DATA
                        : ($effectiveLaborRateCents !== null ? self::money($effectiveLaborRateCents).'/hr' : 'n/a'),
                    $targets['posted_labor_rate_cents'] !== null
                        ? 'Labor sales ÷ billed hours. Door rate '.self::money($targets['posted_labor_rate_cents']).'/hr'
                        : 'Labor sales ÷ billed hours',
                ),
            ],
            shopMetrics: [
                self::shopMetric('ARO', self::money($aroCents)),
                self::shopMetric(
                    'Gross profit / RO',
                    $avgRoProfitCents !== null
                        ? self::money($avgRoProfitCents)
                        : ($costsComplete ? 'n/a' : ReportingStandardsV1::INCOMPLETE_DATA),
                ),
                self::shopMetric(
                    'Gross profit margin',
                    ReportingStandardsV1::percentOrIncomplete($costsComplete, $costsComplete ? $grossMarginPercent : null),
                ),
                self::shopMetric(
                    'Sales / hour',
                    $salesPerHourCents !== null ? self::money($salesPerHourCents).'/hr' : 'n/a',
                ),
                self::shopMetric(
                    'Gross profit / hour',
                    $grossProfitPerHourCents !== null
                        ? self::money($grossProfitPerHourCents).'/hr'
                        : ($costsComplete ? 'n/a' : ReportingStandardsV1::INCOMPLETE_DATA),
                ),
            ],
            roSummary: [
                self::summaryRow('Labor', self::money($components['labor_cents'])),
                self::summaryRow('Parts', self::money($components['parts_cents'])),
                self::summaryRow('Sublet', self::money($components['sublet_cents'])),
                self::summaryRow('Other', self::money($components['fee_cents'])),
                self::summaryRow('Discounts', '-'.self::money($components['discount_cents']), $components['discount_cents'] > 0 ? 'subtract' : null),
                self::summaryRow('Posted invoice sales', self::money($salesCents), 'total'),
                self::summaryRow('Sales tax', self::money($metrics->postedInvoiceTaxCents())),
                self::summaryRow('Invoice total', self::money($metrics->postedInvoiceTotalCents()), 'total'),
            ],
            salesBreakdown: self::salesBreakdownRows($from, $to),
            reconciliation: [
                'reconciles' => $reconciliation['reconciles'],
                'sales_posted' => $reconciliation['posted_ro_summary']['total'],
                'write_offs' => self::money($metrics->writeOffCents()),
                'cash_collected' => (string) $reconciliationRows->firstWhere('key', 'total_cashiered')['amount'],
                'delta_label' => $reconciliation['delta_label'],
            ],
            reportUrl: route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
            ]),
            financialUrl: route('operations.reports.operational', [
                'from' => $fromLabel,
                'to' => $toLabel,
                'tab' => 'financial',
            ]),
        );
    }

    /**
     * @return list<array{category: string, non_taxable: string, taxable: string, net: string}>
     */
    private static function salesBreakdownRows(Carbon $from, Carbon $to): array
    {
        $categories = [
            'Labor' => RepairOrderLineType::Labor,
            'Parts' => RepairOrderLineType::Part,
            'Sublets' => RepairOrderLineType::Sublet,
            'Fees' => RepairOrderLineType::Fee,
        ];

        $rows = [];

        foreach ($categories as $label => $type) {
            $nonTaxable = self::categorySubtotalCents($from, $to, $type, taxable: false);
            $taxable = self::categorySubtotalCents($from, $to, $type, taxable: true);

            $rows[] = [
                'category' => $label,
                'non_taxable' => self::money($nonTaxable),
                'taxable' => self::money($taxable),
                'net' => self::money($nonTaxable + $taxable),
            ];
        }

        return $rows;
    }

    private static function postedCount(Carbon $from, Carbon $to): int
    {
        return OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)->count();
    }

    private static function postedLaborHours(Carbon $from, Carbon $to): float
    {
        return self::postedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->selectRaw('COALESCE(SUM('.self::laborHoursExpression().'), 0) as hours')
            ->value('hours') ?? 0.0;
    }

    private static function presentedLaborHours(Carbon $from, Carbon $to): float
    {
        return (float) RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesPostedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->whereIn('repair_order_concerns.disposition', [
                RepairOrderConcernDisposition::Recommended,
                RepairOrderConcernDisposition::Approved,
                RepairOrderConcernDisposition::Declined,
            ])
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->selectRaw('COALESCE(SUM('.self::laborHoursExpression().'), 0) as hours')
            ->value('hours');
    }

    private static function categorySubtotalCents(
        Carbon $from,
        Carbon $to,
        RepairOrderLineType $type,
        bool $taxable,
    ): int {
        $query = self::postedLineQuery($from, $to)
            ->where('repair_order_lines.type', $type);

        if ($taxable) {
            $query->where('repair_order_lines.tax_cents', '>', 0);
        } else {
            $query->where('repair_order_lines.tax_cents', '<=', 0);
        }

        return (int) $query->sum('repair_order_lines.subtotal_cents');
    }

    /**
     * @return Builder<RepairOrderLine>
     */
    private static function postedLineQuery(Carbon $from, Carbon $to): Builder
    {
        return RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved)
            ->whereIn('repair_order_lines.type', [
                RepairOrderLineType::Labor,
                RepairOrderLineType::Part,
                RepairOrderLineType::Fee,
                RepairOrderLineType::Sublet,
            ])
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesPostedBetweenOnJoinedRepairOrders($query, $from, $to));
    }

    private static function laborHoursExpression(): string
    {
        return OperationalReportTotals::billedHoursSql();
    }

    /**
     * @return array{label: string, value: string, hint: string|null}
     */
    private static function metric(string $label, string $value, ?string $hint = null): array
    {
        return compact('label', 'value', 'hint');
    }

    /** @return array{label: string, value: string} */
    private static function shopMetric(string $label, string $value): array
    {
        return compact('label', 'value');
    }

    /**
     * @return array{label: string, value: string, tone: 'subtract'|'total'|null}
     */
    private static function summaryRow(string $label, string $value, ?string $tone = null): array
    {
        return compact('label', 'value', 'tone');
    }

    private static function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
