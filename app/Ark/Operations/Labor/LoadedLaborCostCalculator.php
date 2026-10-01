<?php

namespace App\Ark\Operations\Labor;

final class LoadedLaborCostCalculator
{
    public const DEFAULT_BURDEN_PERCENT = 28.0;

    public const DEFAULT_BILLABLE_UTILIZATION_PERCENT = 85.0;

    /**
     * Projected effective wage cost per billed hour (not a pay rate).
     *
     * Floor is clock $/hr; at assumed utilization it costs floor ÷ util per billed hour.
     * Flag is already $/billed (flagged) hour. Planning uses the higher of the two.
     */
    public static function effectiveWageCostPerBilledHour(
        float $flagRatePerHour,
        float $floorRatePerHour,
        float $billableUtilizationPercent = self::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
    ): float {
        $flagRatePerHour = max($flagRatePerHour, 0);
        $floorRatePerHour = max($floorRatePerHour, 0);
        $billableUtilizationPercent = max(min($billableUtilizationPercent, 100), 1);

        $floorEquivalentPerBilledHour = $floorRatePerHour > 0
            ? $floorRatePerHour / ($billableUtilizationPercent / 100)
            : 0.0;

        return max($flagRatePerHour, $floorEquivalentPerBilledHour);
    }

    /**
     * Estimate loaded labor cost per billed hour (margin projection).
     *
     * Hourly: spread clock wage across billable utilization, then burden + overhead.
     * Flag: effective wage cost = max(flag, floor ÷ util), then burden + overhead (no second util divisor).
     */
    public static function calculate(
        float $baseWagePerHour,
        float $burdenPercent = self::DEFAULT_BURDEN_PERCENT,
        float $overheadPerHour = 0.0,
        float $billableUtilizationPercent = self::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
        TechnicianLaborPayBasis $payBasis = TechnicianLaborPayBasis::Hourly,
        float $floorRatePerHour = 0.0,
    ): float {
        $baseWagePerHour = max($baseWagePerHour, 0);
        $burdenPercent = max($burdenPercent, 0);
        $overheadPerHour = max($overheadPerHour, 0);
        $billableUtilizationPercent = max(min($billableUtilizationPercent, 100), 1);
        $floorRatePerHour = max($floorRatePerHour, 0);

        if ($payBasis === TechnicianLaborPayBasis::Flag) {
            $effectiveWage = self::effectiveWageCostPerBilledHour(
                $baseWagePerHour,
                $floorRatePerHour,
                $billableUtilizationPercent,
            );
            $payrollLoaded = $effectiveWage * (1 + ($burdenPercent / 100));

            return round($payrollLoaded + $overheadPerHour, 2);
        }

        $payrollLoaded = $baseWagePerHour * (1 + ($burdenPercent / 100));
        $payrollPerBilledHour = $payrollLoaded / ($billableUtilizationPercent / 100);

        return round($payrollPerBilledHour + $overheadPerHour, 2);
    }
}
