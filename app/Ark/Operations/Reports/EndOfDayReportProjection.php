<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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

        $postedCount = self::postedCount($from, $to);
        $postedSalesCents = self::postedSalesCents($from, $to);
        $hoursSold = self::postedLaborHours($from, $to);
        $hoursPresented = self::presentedLaborHours($from, $to);
        $laborSoldCents = self::lineSubtotalCents($from, $to, RepairOrderLineType::Labor);
        $partsGpCents = self::partsGrossProfitCents($from, $to);
        $laborCostCents = OperationalReportTotals::closedLaborCostCents($from, $to);
        $laborGpCents = $laborSoldCents - $laborCostCents;
        $closedGpCents = $partsGpCents + $laborGpCents;

        $effectiveLaborRateCents = $hoursSold > 0 ? (int) round($laborSoldCents / $hoursSold) : null;
        $closeRatioPercent = $hoursPresented > 0
            ? round(($hoursSold / $hoursPresented) * 100, 2)
            : null;
        $avgRoSalesCents = $postedCount > 0 ? (int) round($postedSalesCents / $postedCount) : 0;
        $avgRoProfitCents = $postedCount > 0 ? (int) round($closedGpCents / $postedCount) : 0;
        $avgRoMarginPercent = $postedSalesCents > 0
            ? (int) round(($closedGpCents / $postedSalesCents) * 100)
            : null;
        $grossSalesPerHourCents = $hoursSold > 0 ? (int) round($postedSalesCents / $hoursSold) : null;
        $grossProfitPerHourCents = $hoursSold > 0 ? (int) round($closedGpCents / $hoursSold) : null;

        $feesCents = self::feesCents($from, $to);
        $discountsCents = self::discountsCents($from, $to);
        $taxCents = self::taxCents($from, $to);
        $serviceSalesCents = max(0, $postedSalesCents - $taxCents);
        $subtotalCents = $serviceSalesCents + $feesCents - $discountsCents;

        $fromLabel = OperationalReportDateScope::shopDateString($from);
        $toLabel = OperationalReportDateScope::shopDateString($to);
        $reconciliationRows = collect($reconciliation['rows']);

        return new self(
            rangeLabel: OperationalReportDateScope::shopRangeLabel($from, $to),
            fromDate: $fromLabel,
            toDate: $toLabel,
            salesEffectiveness: [
                self::metric('Total ROs', (string) $postedCount, 'Posted in range'),
                self::metric('Hours Presented', number_format($hoursPresented, 2).' total', 'Labor on opened estimates'),
                self::metric('Hours Sold', number_format($hoursSold, 2).' total', 'Billed labor on posted ROs'),
                self::metric(
                    'Close Ratio',
                    $closeRatioPercent !== null ? number_format($closeRatioPercent, 2).'%' : 'n/a',
                    'Hours sold ÷ hours presented',
                ),
                self::metric(
                    'Effective Labor Rate',
                    $effectiveLaborRateCents !== null ? self::money($effectiveLaborRateCents).'/hr' : 'n/a',
                    $targets['posted_labor_rate_cents'] !== null
                        ? 'Posted rate '.self::money($targets['posted_labor_rate_cents']).'/hr'
                        : 'Labor sold ÷ hours sold',
                ),
            ],
            shopMetrics: [
                self::shopMetric('Avg RO (Sales)', self::money($avgRoSalesCents)),
                self::shopMetric('Avg RO (Profit)', self::money($avgRoProfitCents)),
                self::shopMetric(
                    'Avg RO (Profit Margin)',
                    $avgRoMarginPercent !== null ? $avgRoMarginPercent.'%' : 'n/a',
                ),
                self::shopMetric(
                    'Gross Sales',
                    $grossSalesPerHourCents !== null ? self::money($grossSalesPerHourCents).'/hr' : 'n/a',
                ),
                self::shopMetric(
                    'Gross Profit',
                    $grossProfitPerHourCents !== null ? self::money($grossProfitPerHourCents).'/hr' : 'n/a',
                ),
            ],
            roSummary: [
                self::summaryRow('Sales', self::money($serviceSalesCents)),
                self::summaryRow('Fees', self::money($feesCents)),
                self::summaryRow('Discounts', '-'.self::money($discountsCents), $discountsCents > 0 ? 'subtract' : null),
                self::summaryRow('Subtotal', self::money($subtotalCents), 'total'),
                self::summaryRow('Sales Tax', self::money($taxCents)),
                self::summaryRow('Posted Total', $reconciliation['posted_ro_summary']['total'], 'total'),
            ],
            salesBreakdown: self::salesBreakdownRows($from, $to),
            reconciliation: [
                'reconciles' => $reconciliation['reconciles'],
                'sales_posted' => $reconciliation['posted_ro_summary']['total'],
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

    private static function postedSalesCents(Carbon $from, Carbon $to): int
    {
        return OperationalReportTotals::postedSalesCents(
            OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)->pluck('id'),
        );
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
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applyOpenedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->whereIn('repair_order_concerns.disposition', [
                RepairOrderConcernDisposition::Recommended,
                RepairOrderConcernDisposition::Approved,
            ])
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->selectRaw('COALESCE(SUM('.self::laborHoursExpression().'), 0) as hours')
            ->value('hours');
    }

    private static function lineSubtotalCents(Carbon $from, Carbon $to, RepairOrderLineType $type): int
    {
        return (int) self::postedLineQuery($from, $to)
            ->where('repair_order_lines.type', $type)
            ->sum('repair_order_lines.subtotal_cents');
    }

    private static function feesCents(Carbon $from, Carbon $to): int
    {
        $feeLines = (int) self::postedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Fee)
            ->sum('repair_order_lines.subtotal_cents');
        $allocatedShopFees = (int) self::postedLineQuery($from, $to)
            ->sum('repair_order_lines.shop_fee_cents');

        return $feeLines + $allocatedShopFees;
    }

    private static function discountsCents(Carbon $from, Carbon $to): int
    {
        return (int) self::postedLineQuery($from, $to)
            ->sum('repair_order_lines.standing_discount_cents');
    }

    private static function taxCents(Carbon $from, Carbon $to): int
    {
        return (int) self::postedLineQuery($from, $to)
            ->sum('repair_order_lines.tax_cents');
    }

    private static function partsGrossProfitCents(Carbon $from, Carbon $to): int
    {
        return (int) self::postedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->selectRaw('COALESCE(SUM(subtotal_cents - COALESCE(part_cost_cents, 0)), 0) as gp_cents')
            ->value('gp_cents');
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
        return Schema::hasColumn('repair_order_lines', 'labor_billed_hours')
            ? 'COALESCE(repair_order_lines.labor_billed_hours, repair_order_lines.quantity)'
            : 'repair_order_lines.quantity';
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
