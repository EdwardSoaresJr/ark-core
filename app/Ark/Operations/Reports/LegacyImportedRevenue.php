<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportReport;
use App\Ark\Import\LegacyRepairOrderTimeline;
use Illuminate\Support\Carbon;

/**
 * Mirrors legacy ARK-SMS closed sales authority for audits:
 * invoice total when a legacy invoice exists, otherwise published/approved line totals.
 */
final class LegacyImportedRevenue
{
    public static function closedRevenueCentsForRange(Carbon $from, Carbon $to): int
    {
        try {
            $reader = new DatabaseLegacyArkSmsReader;
        } catch (\Throwable) {
            return 0;
        }

        $mapper = new LegacyArkSmsValueMapper;
        $report = new LegacyImportReport;
        $totalCents = 0;

        foreach ($reader->repairOrders() as $legacy) {
            $legacyRepairOrderId = (int) ($legacy['id'] ?? 0);

            if ($legacyRepairOrderId <= 0) {
                continue;
            }

            $status = $mapper->mapRepairOrderStatus($legacy['status'] ?? null, $report);
            $legacyStatusSlug = strtolower(trim((string) ($legacy['status'] ?? '')));

            if (! LegacyRepairOrderTimeline::statusReceivesCloseDate($status)) {
                continue;
            }

            $openedAt = LegacyRepairOrderTimeline::openedAt($legacy);
            $closedAt = LegacyRepairOrderTimeline::closedAt($legacy, $status, $legacyStatusSlug);

            if ($openedAt === null || $openedAt->lt(OperationalReportDateScope::trustworthyDataStartsAt()) || $openedAt->lt($from) || $openedAt->gt($to)) {
                continue;
            }

            if ($closedAt === null || $closedAt->lt($from) || $closedAt->gt($to)) {
                continue;
            }

            $hasInvoice = false;

            foreach ($reader->invoicesForRepairOrder($legacyRepairOrderId) as $legacyInvoice) {
                $hasInvoice = true;
                $totalCents += $mapper->dollarsToCents($legacyInvoice['total'] ?? 0);
            }

            if ($hasInvoice) {
                continue;
            }

            $publishedConcernIds = [];

            foreach ($reader->concernsForRepairOrder($legacyRepairOrderId) as $legacyConcern) {
                $legacyConcernId = (int) ($legacyConcern['id'] ?? 0);
                $legacyDisposition = strtolower(trim((string) ($legacyConcern['disposition'] ?? '')));

                if ($legacyConcernId <= 0) {
                    continue;
                }

                if (in_array($legacyDisposition, ['published', 'approved', 'authorized', 'paid'], true)) {
                    $publishedConcernIds[$legacyConcernId] = true;
                }
            }

            foreach ($reader->linesForRepairOrder($legacyRepairOrderId) as $legacyLine) {
                $legacyConcernId = (int) ($legacyLine['concern_id'] ?? 0);

                if ($legacyConcernId <= 0 || ! isset($publishedConcernIds[$legacyConcernId])) {
                    continue;
                }

                $lineTotal = $mapper->dollarsToCents($legacyLine['total'] ?? null);

                if ($lineTotal <= 0) {
                    $lineTotal = $mapper->dollarsToCents($legacyLine['subtotal'] ?? 0)
                        + $mapper->dollarsToCents($legacyLine['tax'] ?? 0)
                        + $mapper->dollarsToCents($legacyLine['shop_fee'] ?? 0);
                }

                $totalCents += $lineTotal;
            }
        }

        return $totalCents;
    }
}
