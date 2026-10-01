<?php

namespace App\Ark\Operations\Reports\Standards;

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

/**
 * Formulas from the shop KPI dictionary.
 *
 * Sales is pre-tax. The posted total adds tax back for reconciliation only.
 * approvalRatePercent() is closing ratio (dollars). closeRatioPercent() is closing ratio (hours).
 * approvedSalesPerOpenRepairOrderCents() is open work, not ARO.
 */
final class ReportingStandardsV1
{
    public const INCOMPLETE_DATA = 'Incomplete data';

    public static function percentOrIncomplete(bool $complete, ?int $percent): string
    {
        if (! $complete) {
            return self::INCOMPLETE_DATA;
        }

        if ($percent === null) {
            return 'n/a';
        }

        return $percent.'%';
    }

    public static function preTaxServiceSalesCents(
        int $laborCents,
        int $partsCents,
        int $subletCents,
        int $feeCents,
        int $discountCents,
    ): int {
        return $laborCents + $partsCents + $subletCents + $feeCents - $discountCents;
    }

    public static function postedSalesCents(
        int $laborCents,
        int $partsCents,
        int $subletCents,
        int $feeCents,
        int $discountCents,
        int $taxCents,
    ): int {
        return self::preTaxServiceSalesCents(
            $laborCents,
            $partsCents,
            $subletCents,
            $feeCents,
            $discountCents,
        ) + $taxCents;
    }

    /**
     * Posted invoice sales. Uses the frozen pre-tax field when the invoice stored one.
     * Older invoices use invoice total minus tax.
     */
    public static function postedInvoiceSalesCents(?int $subtotalBeforeTaxCents, int $invoiceTotalCents, int $taxCents): int
    {
        if ($subtotalBeforeTaxCents !== null) {
            return $subtotalBeforeTaxCents;
        }

        return $invoiceTotalCents - $taxCents;
    }

    public static function aroCents(int $preTaxServiceSalesCents, int $postedRepairOrderCount): int
    {
        if ($postedRepairOrderCount < 1) {
            return 0;
        }

        return (int) round($preTaxServiceSalesCents / $postedRepairOrderCount);
    }

    public static function approvedSalesPerOpenRepairOrderCents(int $approvedPreTaxServiceSalesCents, int $openRepairOrderCount): int
    {
        if ($openRepairOrderCount < 1) {
            return 0;
        }

        return (int) round($approvedPreTaxServiceSalesCents / $openRepairOrderCount);
    }

    public static function cashCollectedCents(int $receiptCents, int $refundCents): int
    {
        return $receiptCents - $refundCents;
    }

    public static function grossProfitCents(int $preTaxSalesCents, int $matchedCostCents): int
    {
        return $preTaxSalesCents - $matchedCostCents;
    }

    /**
     * Whole percent. Null when there is no pre-tax sales basis.
     */
    public static function grossMarginPercent(int $preTaxSalesCents, int $matchedCostCents): ?int
    {
        if ($preTaxSalesCents <= 0) {
            return null;
        }

        return (int) round((self::grossProfitCents($preTaxSalesCents, $matchedCostCents) / $preTaxSalesCents) * 100);
    }

    /**
     * Closing ratio (dollars). Null when nothing was presented.
     */
    public static function approvalRatePercent(int $approvedCents, int $presentedCents): ?float
    {
        if ($presentedCents <= 0) {
            return null;
        }

        return round(($approvedCents / $presentedCents) * 100, 1);
    }

    /**
     * Repair orders with any approved concern. This is a count, not a closing ratio.
     */
    public static function approvedRepairOrderRatePercent(int $approvedRepairOrderCount, int $repairOrderCount): ?float
    {
        if ($repairOrderCount < 1) {
            return null;
        }

        return round(($approvedRepairOrderCount / $repairOrderCount) * 100, 1);
    }

    /**
     * Closing ratio (hours). Null when no hours were presented.
     */
    public static function closeRatioPercent(float $hoursSold, float $hoursPresented): ?float
    {
        if ($hoursPresented <= 0) {
            return null;
        }

        return round(($hoursSold / $hoursPresented) * 100, 1);
    }

    public static function countsAsApprovedRevenue(RepairOrderConcernDisposition $disposition): bool
    {
        return $disposition === RepairOrderConcernDisposition::Approved;
    }
}
