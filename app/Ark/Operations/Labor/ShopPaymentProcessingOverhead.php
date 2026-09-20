<?php

namespace App\Ark\Operations\Labor;

final class ShopPaymentProcessingOverhead
{
    public const DEFAULT_CARD_PROCESSING_PERCENT = 2.9;

    /**
     * Card processor fee on gross card volume (Stripe, Square, etc.).
     */
    public static function monthlyProcessingCost(float $monthlyCardVolume, float $processingPercent): float
    {
        $monthlyCardVolume = max($monthlyCardVolume, 0);
        $processingPercent = max($processingPercent, 0);

        return round($monthlyCardVolume * ($processingPercent / 100), 2);
    }

    /**
     * Merchant loan / advance holdback on card deposits after processing fees.
     */
    public static function monthlyFinancingCost(
        float $monthlyCardVolume,
        float $processingPercent,
        float $financingHoldbackPercent,
        ?float $fixedMonthlyFinancingPayment = null,
    ): float {
        if ($fixedMonthlyFinancingPayment !== null && $fixedMonthlyFinancingPayment > 0) {
            return round($fixedMonthlyFinancingPayment, 2);
        }

        $monthlyCardVolume = max($monthlyCardVolume, 0);
        $processingPercent = max($processingPercent, 0);
        $financingHoldbackPercent = max($financingHoldbackPercent, 0);

        if ($monthlyCardVolume <= 0 || $financingHoldbackPercent <= 0) {
            return 0.0;
        }

        $netAfterProcessing = $monthlyCardVolume - self::monthlyProcessingCost($monthlyCardVolume, $processingPercent);

        return round(max($netAfterProcessing, 0) * ($financingHoldbackPercent / 100), 2);
    }

    public static function monthlyPaymentOverheadTotal(
        float $monthlyCardVolume,
        float $processingPercent,
        float $financingHoldbackPercent,
        ?float $fixedMonthlyFinancingPayment = null,
    ): float {
        return round(
            self::monthlyProcessingCost($monthlyCardVolume, $processingPercent)
            + self::monthlyFinancingCost(
                $monthlyCardVolume,
                $processingPercent,
                $financingHoldbackPercent,
                $fixedMonthlyFinancingPayment,
            ),
            2,
        );
    }
}
