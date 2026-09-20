<?php

namespace App\Ark\Operations\Labor;

final class ShopOverheadCalculator
{
    public const DEFAULT_WORKDAYS_PER_MONTH = 22.0;

    public const DEFAULT_WORKDAY_HOURS = 8.0;

    public const DEFAULT_BILLABLE_UTILIZATION_PERCENT = 85.0;

    /**
     * Allocate monthly shop overhead across expected billed labor hours.
     *
     * monthly_billable_hours = technicians × workdays × workday_hours × (utilization / 100)
     */
    public static function calculate(
        float $monthlyOverhead,
        int $technicianCount,
        float $workdayHours = self::DEFAULT_WORKDAY_HOURS,
        float $billableUtilizationPercent = self::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
        float $workdaysPerMonth = self::DEFAULT_WORKDAYS_PER_MONTH,
    ): ?float {
        $monthlyOverhead = max($monthlyOverhead, 0);
        $technicianCount = max($technicianCount, 0);
        $workdayHours = max($workdayHours, 0);
        $billableUtilizationPercent = max(min($billableUtilizationPercent, 100), 1);
        $workdaysPerMonth = max($workdaysPerMonth, 0);

        if ($monthlyOverhead <= 0 || $technicianCount === 0 || $workdayHours <= 0 || $workdaysPerMonth <= 0) {
            return null;
        }

        $monthlyBillableHours = $technicianCount
            * $workdaysPerMonth
            * $workdayHours
            * ($billableUtilizationPercent / 100);

        if ($monthlyBillableHours <= 0) {
            return null;
        }

        return round($monthlyOverhead / $monthlyBillableHours, 2);
    }

    public static function monthlyBillableHours(
        int $technicianCount,
        float $workdayHours = self::DEFAULT_WORKDAY_HOURS,
        float $billableUtilizationPercent = self::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
        float $workdaysPerMonth = self::DEFAULT_WORKDAYS_PER_MONTH,
    ): float {
        $technicianCount = max($technicianCount, 0);
        $workdayHours = max($workdayHours, 0);
        $billableUtilizationPercent = max(min($billableUtilizationPercent, 100), 1);
        $workdaysPerMonth = max($workdaysPerMonth, 0);

        return round(
            $technicianCount * $workdaysPerMonth * $workdayHours * ($billableUtilizationPercent / 100),
            1,
        );
    }
}
