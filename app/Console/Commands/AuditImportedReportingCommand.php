<?php

namespace App\Console\Commands;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Reports\LegacyImportedRevenue;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportTotals;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditImportedReportingCommand extends Command
{
    protected $signature = 'ark:audit-imported-reporting
        {--from= : Range start (Y-m-d)}
        {--to= : Range end (Y-m-d)}';

    protected $description = 'Compare imported reporting totals against legacy authority and sold-line filters.';

    public function handle(): int
    {
        $from = Carbon::parse($this->option('from') ?: now()->startOfYear()->toDateString())->startOfDay();
        $to = Carbon::parse($this->option('to') ?: now()->toDateString())->endOfDay();

        $completedIds = OperationalReportDateScope::salesClosedBetween(RepairOrder::query(), $from, $to)
            ->whereColumn('repair_order_id', '!=', 'id')
            ->pluck('id');

        $carryoverClosedIds = OperationalReportDateScope::closedBetween(RepairOrder::query(), $from, $to)
            ->whereColumn('repair_order_id', '!=', 'id')
            ->where(DB::raw(OperationalReportDateScope::openedAtSql()), '<', $from)
            ->pluck('id');

        $archivedOnlyIds = OperationalReportDateScope::archiveClosedBetween(RepairOrder::query(), $from, $to)
            ->whereColumn('repair_order_id', '!=', 'id')
            ->pluck('id');

        $allLines = (int) DB::table('repair_order_lines')
            ->whereIn('repair_order_id', $completedIds)
            ->whereIn('type', ['labor', 'part', 'fee'])
            ->selectRaw('COALESCE(SUM(subtotal_cents + tax_cents + shop_fee_cents), 0) as cents')
            ->value('cents');

        $approvedLines = (int) OperationalReportTotals::soldLineQuery()
            ->whereIn('repair_order_lines.repair_order_id', $completedIds)
            ->selectRaw('COALESCE(SUM(repair_order_lines.total_cents), 0) as cents')
            ->value('cents');

        $reportSales = OperationalReportTotals::closedRevenueCents($completedIds);

        $archivedSales = OperationalReportTotals::closedRevenueCents($archivedOnlyIds);

        $invoiceOnly = (int) OperationalReportTotals::legacyInvoiceTotalCentsByRepairOrderId($completedIds)->sum();

        $legacyAuthority = LegacyImportedRevenue::closedRevenueCentsForRange($from, $to);

        $deltaCents = $reportSales - $legacyAuthority;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Range', $from->toDateString().' → '.$to->toDateString()],
                ['Imported sales ROs (opened + closed in range)', (string) $completedIds->count()],
                ['Prior-year opens closed in range (excluded)', (string) $carryoverClosedIds->count()],
                ['Imported status=closed only (stale KPI)', (string) $archivedOnlyIds->count()],
                ['All line subtotal+tax+shop (unsold estimate noise)', '$'.number_format($allLines / 100, 2)],
                ['Approved sold line totals', '$'.number_format($approvedLines / 100, 2)],
                ['Legacy invoice snapshots only', '$'.number_format($invoiceOnly / 100, 2)],
                ['V2 Sales Closed (completed scope)', '$'.number_format($reportSales / 100, 2)],
                ['V2 Sales if status=closed only (stale)', '$'.number_format($archivedSales / 100, 2)],
                ['Legacy ARK-SMS authority', '$'.number_format($legacyAuthority / 100, 2)],
                ['Delta (V2 − legacy)', '$'.number_format($deltaCents / 100, 2).' ('.($deltaCents === 0 ? 'match' : 'investigate').')'],
            ],
        );

        $this->newLine();

        if ($archivedOnlyIds->count() !== $completedIds->count()) {
            $this->warn('Sales Closed previously used status=closed only and excluded ready_pickup ROs legacy still counts as completed.');
        }

        if (abs($deltaCents) > 10_000) {
            $this->warn('Mismatch exceeds $100. Run ark:backfill-imported-concern-dispositions and ark:backfill-imported-repair-order-dates, then re-audit.');
        } else {
            $this->info('V2 completed-scope sales align with legacy invoice + published-line authority.');
        }

        return self::SUCCESS;
    }
}
