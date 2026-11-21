<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
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
     * Invoice total for posted repair orders. Tax is included. A missing invoice is zero.
     * Courtesy, trade, and goodwill stay at the invoice total. Write-offs are a separate total.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     */
    public static function postedSalesCents(Collection|array $repairOrderIds): int
    {
        return (int) self::postedSalesCentsByRepairOrderId($repairOrderIds)->sum();
    }

    /**
     * Frozen invoice total per repair order. Does not read today's repair-order lines.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function postedSalesCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();
        $amounts = self::frozenInvoiceAmountsByRepairOrderId($ids);

        return $ids->mapWithKeys(function (int $repairOrderId) use ($amounts): array {
            return [$repairOrderId => (int) ($amounts[$repairOrderId]['total_cents'] ?? 0)];
        });
    }

    /**
     * Posted invoice sales, tax, and invoice total for repair orders posted in the range.
     * A repair order with no invoice is not a mismatch. Lines match when every stored
     * invoice still foots to today's approved pre-tax lines.
     *
     * @return array{sales_cents: int, tax_cents: int, total_cents: int, lines_match: bool}
     */
    public static function postedInvoiceFigures(Carbon $from, Carbon $to): array
    {
        $ids = OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)->pluck('id');
        $amounts = self::frozenInvoiceAmountsByRepairOrderId($ids);
        $linePreTax = self::approvedPreTaxCentsByRepairOrderId($ids);

        $linesMatch = $ids->every(function (int $repairOrderId) use ($amounts, $linePreTax): bool {
            if (! $amounts->has($repairOrderId)) {
                return true;
            }

            return (int) $amounts[$repairOrderId]['pre_tax_cents'] === (int) ($linePreTax[$repairOrderId] ?? 0);
        });

        return [
            'sales_cents' => (int) $amounts->sum('pre_tax_cents'),
            'tax_cents' => (int) $amounts->sum('tax_cents'),
            'total_cents' => (int) $amounts->sum('total_cents'),
            'lines_match' => $linesMatch,
        ];
    }

    public static function writeOffCents(Carbon $from, Carbon $to): int
    {
        return (int) RepairOrderLedgerEntry::query()
            ->active()
            ->where('entry_type', LedgerEntryType::WriteOff)
            ->whereBetween('recorded_at', [$from, $to])
            ->sum('amount_cents');
    }

    /**
     * Authoritative posted sales - alias for {@see postedSalesCents()}.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     */
    public static function closedRevenueCents(Collection|array $repairOrderIds): int
    {
        return self::postedSalesCents($repairOrderIds);
    }

    /**
     * Sold total per repair order. Prefers a frozen invoice snapshot when one exists;
     * otherwise uses {@see EstimateTotalsCalculator::approvedTotalsForRead()} so unapproved
     * added lines and price/quantity increases are not counted as sales (#74).
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function soldTotalCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $frozen = self::frozenInvoiceAmountsByRepairOrderId($ids);
        $needsApproved = $ids->reject(fn (int $repairOrderId): bool => $frozen->has($repairOrderId))->values();
        $approved = self::approvedSoldFiguresByRepairOrderId($needsApproved);

        return $ids->mapWithKeys(function (int $repairOrderId) use ($frozen, $approved): array {
            if ($frozen->has($repairOrderId)) {
                return [$repairOrderId => (int) $frozen[$repairOrderId]['total_cents']];
            }

            return [$repairOrderId => (int) ($approved[$repairOrderId]['total_cents'] ?? 0)];
        });
    }

    /**
     * Approved labor subtotals per repair order (canonical approved-dollar authority).
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    public static function laborCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        return self::approvedSoldFiguresByRepairOrderId($repairOrderIds)
            ->map(fn (array $figures): int => (int) $figures['labor_cents']);
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
     * Posted sales mix from canonical approved dollars (not raw living line totals).
     *
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
        $ids = OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)->pluck('id');
        $orders = RepairOrder::query()
            ->with(['lines.concern', 'concerns', 'assignedTechnician:id,labor_cost_cents'])
            ->whereIn('id', $ids)
            ->get();

        $calculator = app(EstimateTotalsCalculator::class);
        $laborCents = 0;
        $partsCents = 0;
        $subletCents = 0;
        $feeLineCents = 0;
        $shopFeeCents = 0;
        $discountCents = 0;
        $taxCents = 0;
        $partsGpCents = 0;
        $partsMissingCostCents = 0;
        $laborMissingCostCents = 0;

        foreach ($orders as $repairOrder) {
            $approvedLines = $calculator->approvedTotalsForRead($repairOrder)->lines
                ->filter(fn (RepairOrderLine $line): bool => in_array($line->type, [
                    RepairOrderLineType::Labor,
                    RepairOrderLineType::Part,
                    RepairOrderLineType::Fee,
                    RepairOrderLineType::Sublet,
                ], true));

            $techCostMissing = $repairOrder->assigned_technician_id === null
                || $repairOrder->assignedTechnician?->labor_cost_cents === null;

            foreach ($approvedLines as $line) {
                $subtotal = (int) $line->subtotal_cents;
                $shopFeeCents += (int) $line->shop_fee_cents;
                $discountCents += (int) $line->standing_discount_cents;
                $taxCents += (int) $line->tax_cents;

                match ($line->type) {
                    RepairOrderLineType::Labor => $laborCents += $subtotal,
                    RepairOrderLineType::Part => $partsCents += $subtotal,
                    RepairOrderLineType::Sublet => $subletCents += $subtotal,
                    RepairOrderLineType::Fee => $feeLineCents += $subtotal,
                    default => null,
                };

                if ($line->type === RepairOrderLineType::Part) {
                    if ($line->part_cost_cents === null) {
                        $partsMissingCostCents += $subtotal;
                    } else {
                        $partsGpCents += $subtotal - (int) round(((float) $line->quantity) * (int) $line->part_cost_cents);
                    }
                }

                if ($line->type === RepairOrderLineType::Labor && $techCostMissing) {
                    $laborMissingCostCents += $subtotal;
                }
            }
        }

        return [
            'labor_cents' => $laborCents,
            'parts_cents' => $partsCents,
            'sublet_cents' => $subletCents,
            'fee_cents' => $feeLineCents + $shopFeeCents,
            'discount_cents' => $discountCents,
            'tax_cents' => $taxCents,
            'parts_gp_cents' => $partsGpCents,
            'parts_sales_missing_cost_cents' => $partsMissingCostCents,
            'labor_sales_missing_cost_cents' => $laborMissingCostCents,
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
     * Closed labor sales by assigned technician, using approved labor dollars only (#74).
     *
     * @return Collection<int, int>
     */
    public static function closedLaborSalesCentsByTechnicianId(Carbon $from, Carbon $to): Collection
    {
        $repairOrders = OperationalReportDateScope::salesClosedBetween(RepairOrder::query(), $from, $to)
            ->whereNotNull('assigned_technician_id')
            ->get(['id', 'assigned_technician_id']);

        if ($repairOrders->isEmpty()) {
            return collect();
        }

        $approvedLabor = self::approvedSoldFiguresByRepairOrderId($repairOrders->pluck('id'));

        return $repairOrders
            ->groupBy('assigned_technician_id')
            ->map(function (Collection $rows) use ($approvedLabor): int {
                return (int) $rows->sum(
                    fn (RepairOrder $repairOrder): int => (int) ($approvedLabor[$repairOrder->id]['labor_cents'] ?? 0),
                );
            });
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
     * Approved pre-tax dollars per repair order (canonical approved-dollar authority).
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, int>
     */
    private static function approvedPreTaxCentsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        return self::approvedSoldFiguresByRepairOrderId($repairOrderIds)
            ->map(fn (array $figures): int => (int) $figures['pre_tax_cents']);
    }

    /**
     * Canonical approved sold figures via {@see EstimateTotalsCalculator::approvedTotalsForRead()}.
     *
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, array{total_cents: int, labor_cents: int, parts_cents: int, pre_tax_cents: int}>
     */
    public static function approvedSoldFiguresByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $orders = RepairOrder::query()
            ->with(['lines.concern', 'concerns'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $calculator = app(EstimateTotalsCalculator::class);

        return $ids->mapWithKeys(function (int $repairOrderId) use ($orders, $calculator): array {
            $repairOrder = $orders->get($repairOrderId);

            if (! $repairOrder instanceof RepairOrder) {
                return [$repairOrderId => [
                    'total_cents' => 0,
                    'labor_cents' => 0,
                    'parts_cents' => 0,
                    'pre_tax_cents' => 0,
                ]];
            }

            $totals = $calculator->approvedTotalsForRead($repairOrder);
            $laborCents = (int) $totals->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Labor)
                ->sum('subtotal_cents');
            $partsCents = (int) $totals->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Part)
                ->sum('subtotal_cents');

            return [$repairOrderId => [
                'total_cents' => $totals->totalCents(),
                'labor_cents' => $laborCents,
                'parts_cents' => $partsCents,
                'pre_tax_cents' => $totals->subtotalBeforeTaxCents(),
            ]];
        });
    }

    /**
     * @param  Collection<int, int>|array<int, int|string>  $repairOrderIds
     * @return Collection<int, array{pre_tax_cents: int, tax_cents: int, total_cents: int}>
     */
    private static function frozenInvoiceAmountsByRepairOrderId(Collection|array $repairOrderIds): Collection
    {
        $ids = collect($repairOrderIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $documents = EstimateDocument::query()
            ->whereIn('repair_order_id', $ids)
            ->where(function (Builder $query): void {
                $query->whereNotNull('legacy_arksms_invoice_id')
                    ->orWhere(function (Builder $query): void {
                        $query->where('document_type', FinancialDocumentType::Invoice->value)
                            ->where('status', '!=', InvoiceStatus::Voided->value);
                    });
            })
            ->orderByDesc('id')
            ->get()
            ->groupBy('repair_order_id');

        return $documents->mapWithKeys(function (Collection $group, int|string $repairOrderId): array {
            $legacy = $group->first(
                fn (EstimateDocument $document): bool => data_get($document->snapshot_json, 'schema_version') === 'legacy_import',
            ) ?? $group->first(
                fn (EstimateDocument $document): bool => $document->legacy_arksms_invoice_id !== null,
            );

            $issued = $group->first(function (EstimateDocument $document): bool {
                return $document->document_type === FinancialDocumentType::Invoice
                    && $document->status !== InvoiceStatus::Voided->value;
            });

            $amounts = self::invoiceAmountsFromSnapshot(($legacy ?? $issued)?->snapshot_json ?? []);

            if ($amounts === null) {
                return [];
            }

            return [(int) $repairOrderId => $amounts];
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{pre_tax_cents: int, tax_cents: int, total_cents: int}|null
     */
    private static function invoiceAmountsFromSnapshot(array $snapshot): ?array
    {
        $totals = $snapshot['totals'] ?? null;

        if (! is_array($totals) || ! array_key_exists('total_cents', $totals)) {
            return null;
        }

        $totalCents = (int) $totals['total_cents'];
        $taxCents = (int) ($totals['tax_cents'] ?? 0);

        return [
            'pre_tax_cents' => ReportingStandardsV1::postedInvoiceSalesCents(
                array_key_exists('subtotal_before_tax_cents', $totals) ? (int) $totals['subtotal_before_tax_cents'] : null,
                $totalCents,
                $taxCents,
            ),
            'tax_cents' => $taxCents,
            'total_cents' => $totalCents,
        ];
    }

    /**
     * Imported legacy invoice totals (frozen snapshot only - not living estimate rebuilds).
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
