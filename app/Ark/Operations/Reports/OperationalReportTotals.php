<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OperationalReportTotals
{
    /**
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function totalCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        return self::soldTotalCentsByRepairOrderId($repairOrderIds);
    }

    /**
     * Posted sales total — matches Tekmetric EOD / payment reconciliation posted RO summary.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     */
    public static function postedSalesCents(Collection|array $repairOrderIds): int
    {
        return (int) self::postedSalesCentsByRepairOrderId($repairOrderIds)->sum();
    }

    /**
     * Posted sales per repair order — legacy import snapshot, issued invoice snapshot, or approved sold lines (incl. sublet).
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function postedSalesCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $legacyTotals = self::legacyInvoiceTotalCentsByRepairOrderId($ids);
        $calculator = app(BalanceDueCalculator::class);

        $repairOrders = RepairOrder::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $idsNeedingLineTotals = $ids
            ->diff($legacyTotals->keys())
            ->filter(function (int $repairOrderId) use ($calculator, $repairOrders): bool {
                $repairOrder = $repairOrders->get($repairOrderId);

                return $repairOrder !== null && $calculator->issuedInvoice($repairOrder) === null;
            });

        $lineTotalsByRepairOrder = $idsNeedingLineTotals->isEmpty()
            ? collect()
            : self::postedSoldLineQuery()
                ->whereIn('repair_order_lines.repair_order_id', $idsNeedingLineTotals)
                ->groupBy('repair_order_lines.repair_order_id')
                ->select('repair_order_lines.repair_order_id')
                ->selectRaw('COALESCE(SUM(repair_order_lines.total_cents), 0) as total_cents')
                ->pluck('total_cents', 'repair_order_id')
                ->map(fn (mixed $cents): int => (int) $cents);

        return $ids->mapWithKeys(function (int $repairOrderId) use ($legacyTotals, $calculator, $repairOrders, $lineTotalsByRepairOrder): array {
            $repairOrder = $repairOrders->get($repairOrderId);

            if ($repairOrder === null) {
                return [$repairOrderId => 0];
            }

            $disposition = RepairOrderCollectionDisposition::tryFromMixed($repairOrder->collection_disposition);

            if ($disposition->excludesFromPostedSales()) {
                return [$repairOrderId => 0];
            }

            if ($legacyTotals->has($repairOrderId)) {
                return [$repairOrderId => (int) $legacyTotals[$repairOrderId]];
            }

            $invoice = $calculator->issuedInvoice($repairOrder);

            if ($invoice !== null) {
                return [$repairOrderId => InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? [])];
            }

            return [$repairOrderId => (int) ($lineTotalsByRepairOrder[$repairOrderId] ?? 0)];
        });
    }

    /**
     * Authoritative posted sales — alias for {@see postedSalesCents()}.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     */
    public static function closedRevenueCents(Collection|array $repairOrderIds): int
    {
        return self::postedSalesCents($repairOrderIds);
    }

    /**
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function soldTotalCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $invoiceTotalsByRepairOrder = self::legacyInvoiceTotalCentsByRepairOrderId($ids);

        $lineTotals = self::soldLineQuery()
            ->whereIn('repair_order_lines.repair_order_id', $ids)
            ->groupBy('repair_order_lines.repair_order_id')
            ->select('repair_order_lines.repair_order_id')
            ->selectRaw('COALESCE(SUM(repair_order_lines.total_cents), 0) as total_cents')
            ->pluck('total_cents', 'repair_order_id')
            ->map(fn (mixed $cents): int => (int) $cents);

        return $ids->mapWithKeys(function (int $repairOrderId) use ($invoiceTotalsByRepairOrder, $lineTotals): array {
            if ($invoiceTotalsByRepairOrder->has($repairOrderId)) {
                return [$repairOrderId => (int) $invoiceTotalsByRepairOrder[$repairOrderId]];
            }

            return [$repairOrderId => (int) ($lineTotals[$repairOrderId] ?? 0)];
        });
    }

    /**
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function laborCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return self::soldLineQuery()
            ->whereIn('repair_order_lines.repair_order_id', $ids)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->groupBy('repair_order_lines.repair_order_id')
            ->select('repair_order_lines.repair_order_id')
            ->selectRaw('COALESCE(SUM(repair_order_lines.subtotal_cents), 0) as labor_cents')
            ->pluck('labor_cents', 'repair_order_id')
            ->map(fn (mixed $cents): int => (int) $cents);
    }

    public static function sumTotalCents(Collection|array $repairOrderIds): int
    {
        return (int) self::soldTotalCentsByRepairOrderId($repairOrderIds)->sum();
    }

    public static function cashCollectedCents(Carbon $from, Carbon $to): int
    {
        return self::netCashCents(
            RepairOrderLedgerEntry::query()
                ->active()
                ->whereBetween('recorded_at', [$from, $to]),
        );
    }

    /**
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     */
    public static function cashCollectedCentsForRepairOrders(Collection|array $repairOrderIds, Carbon $from, Carbon $to): int
    {
        $ids = collect($repairOrderIds)->filter()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return self::netCashCents(
            RepairOrderLedgerEntry::query()
                ->active()
                ->whereIn('repair_order_id', $ids)
                ->whereBetween('recorded_at', [$from, $to]),
        );
    }

    /**
     * Payments and deposits minus refunds. Voided entries are already excluded by the caller.
     *
     * @param  Builder<RepairOrderLedgerEntry>  $query
     */
    public static function netCashCents(Builder $query): int
    {
        return (int) $query
            ->whereIn('entry_type', [
                LedgerEntryType::Payment,
                LedgerEntryType::Deposit,
                LedgerEntryType::Refund,
            ])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN -amount_cents ELSE amount_cents END), 0) as net_cents',
                [LedgerEntryType::Refund->value],
            )
            ->value('net_cents');
    }

    /**
     * @return array{
     *     labor_cents: int,
     *     parts_cents: int,
     *     sublet_cents: int,
     *     fee_cents: int,
     *     discount_cents: int,
     *     tax_cents: int,
     *     parts_gp_cents: int,
     *     parts_sales_missing_cost_cents: int,
     *     labor_sales_missing_cost_cents: int
     * }
     */
    public static function postedSalesComponents(Carbon $from, Carbon $to): array
    {
        $row = self::postedApprovedLineQuery($from, $to)
            ->leftJoin('users as assigned_technicians', 'assigned_technicians.id', '=', 'repair_orders.assigned_technician_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as labor_cents',
                [RepairOrderLineType::Labor->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as parts_cents',
                [RepairOrderLineType::Part->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as sublet_cents',
                [RepairOrderLineType::Sublet->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as fee_line_cents',
                [RepairOrderLineType::Fee->value],
            )
            ->selectRaw('COALESCE(SUM(repair_order_lines.shop_fee_cents), 0) as shop_fee_cents')
            ->selectRaw('COALESCE(SUM(repair_order_lines.standing_discount_cents), 0) as discount_cents')
            ->selectRaw('COALESCE(SUM(repair_order_lines.tax_cents), 0) as tax_cents')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? AND repair_order_lines.part_cost_cents IS NULL THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as parts_sales_missing_cost_cents',
                [RepairOrderLineType::Part->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? AND repair_order_lines.part_cost_cents IS NOT NULL THEN repair_order_lines.subtotal_cents - ROUND(repair_order_lines.quantity * repair_order_lines.part_cost_cents) ELSE 0 END), 0) as parts_gp_cents',
                [RepairOrderLineType::Part->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? AND (repair_orders.assigned_technician_id IS NULL OR assigned_technicians.labor_cost_cents IS NULL) THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as labor_sales_missing_cost_cents',
                [RepairOrderLineType::Labor->value],
            )
            ->first();

        return [
            'labor_cents' => (int) ($row->labor_cents ?? 0),
            'parts_cents' => (int) ($row->parts_cents ?? 0),
            'sublet_cents' => (int) ($row->sublet_cents ?? 0),
            'fee_cents' => (int) ($row->fee_line_cents ?? 0) + (int) ($row->shop_fee_cents ?? 0),
            'discount_cents' => (int) ($row->discount_cents ?? 0),
            'tax_cents' => (int) ($row->tax_cents ?? 0),
            'parts_gp_cents' => (int) ($row->parts_gp_cents ?? 0),
            'parts_sales_missing_cost_cents' => (int) ($row->parts_sales_missing_cost_cents ?? 0),
            'labor_sales_missing_cost_cents' => (int) ($row->labor_sales_missing_cost_cents ?? 0),
        ];
    }

    /**
     * @return Builder<RepairOrderLine>
     */
    public static function postedApprovedLineQuery(Carbon $from, Carbon $to): Builder
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

    public static function billedHoursSql(): string
    {
        return self::laborHoursExpression();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    public static function sumTotalCentsFor(Collection $repairOrders, Collection $totalsByRepairOrderId): int
    {
        return (int) $repairOrders->sum(
            fn ($repairOrder): int => (int) ($totalsByRepairOrderId[$repairOrder->id] ?? 0),
        );
    }

    public static function closedLaborCostCents(Carbon $from, Carbon $to): int
    {
        return (int) self::closedLaborGroupedByTechnicianQuery($from, $to)
            ->leftJoin('users as assigned_technicians', 'assigned_technicians.id', '=', 'repair_orders.assigned_technician_id')
            ->selectRaw('COALESCE(SUM(('.self::laborHoursExpression().') * COALESCE(assigned_technicians.labor_cost_cents, 0)), 0) as labor_cost_cents')
            ->value('labor_cost_cents');
    }

    /**
     * @return Collection<int, float>
     */
    public static function closedLaborHoursByTechnicianId(Carbon $from, Carbon $to): Collection
    {
        return self::closedLaborGroupedByTechnicianQuery($from, $to)
            ->selectRaw('repair_orders.assigned_technician_id')
            ->selectRaw('COALESCE(SUM('.self::laborHoursExpression().'), 0) as billed_hours')
            ->pluck('billed_hours', 'assigned_technician_id')
            ->map(fn (mixed $hours): float => (float) $hours);
    }

    /**
     * @return Collection<int, int>
     */
    public static function closedLaborSalesCentsByTechnicianId(Carbon $from, Carbon $to): Collection
    {
        return self::closedLaborGroupedByTechnicianQuery($from, $to)
            ->selectRaw('repair_orders.assigned_technician_id')
            ->selectRaw('COALESCE(SUM(repair_order_lines.subtotal_cents), 0) as sales_cents')
            ->pluck('sales_cents', 'assigned_technician_id')
            ->map(fn (mixed $cents): int => (int) $cents);
    }

    /**
     * @return Builder<RepairOrderLine>
     */
    private static function closedLaborGroupedByTechnicianQuery(Carbon $from, Carbon $to): Builder
    {
        return self::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->whereNotNull('repair_orders.assigned_technician_id')
            ->groupBy('repair_orders.assigned_technician_id');
    }

    private static function laborHoursExpression(): string
    {
        return Schema::hasColumn('repair_order_lines', 'labor_billed_hours')
            ? 'COALESCE(repair_order_lines.labor_billed_hours, repair_order_lines.quantity)'
            : 'repair_order_lines.quantity';
    }

    /**
     * @return Builder<RepairOrderLine>
     */
    public static function soldLineQuery(): Builder
    {
        return RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved)
            ->whereIn('repair_order_lines.type', [
                RepairOrderLineType::Labor,
                RepairOrderLineType::Part,
                RepairOrderLineType::Fee,
            ]);
    }

    /**
     * Approved sold lines for posted sales fallback — includes sublet revenue.
     *
     * @return Builder<RepairOrderLine>
     */
    private static function postedSoldLineQuery(): Builder
    {
        return RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved)
            ->whereIn('repair_order_lines.type', [
                RepairOrderLineType::Labor,
                RepairOrderLineType::Part,
                RepairOrderLineType::Fee,
                RepairOrderLineType::Sublet,
            ]);
    }

    /**
     * Imported legacy invoice totals (frozen snapshot only — not living estimate rebuilds).
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function legacyInvoiceTotalCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return EstimateDocument::query()
            ->whereIn('repair_order_id', $ids)
            ->whereNotNull('legacy_arksms_invoice_id')
            ->orderByDesc('id')
            ->get()
            ->groupBy('repair_order_id')
            ->map(function (Collection $documents): int {
                $document = $documents->first(
                    fn (EstimateDocument $candidate): bool => data_get($candidate->snapshot_json, 'schema_version') === 'legacy_import',
                ) ?? $documents->first();

                return (int) data_get($document?->snapshot_json, 'totals.total_cents', 0);
            });
    }
}
