<?php

namespace App\Ark\Operations\Labor;

/**
 * Dated shop guidance for Flag technician hourly floors.
 * Configuration informs agreements — it does not rewrite them.
 */
final class TechnicianFloorWageSuggestion
{
    public static function amountCents(): int
    {
        return max(0, (int) config('technician_compensation.floor_wage_suggestion.amount_cents', 1516));
    }

    public static function dollars(): float
    {
        return round(self::amountCents() / 100, 2);
    }

    public static function effectiveYear(): int
    {
        return (int) config('technician_compensation.floor_wage_suggestion.effective_year', 2026);
    }

    public static function label(): string
    {
        $base = (string) config(
            'technician_compensation.floor_wage_suggestion.label',
            'Colorado statewide minimum wage suggestion',
        );

        return self::effectiveYear().' '.$base;
    }

    public static function formattedDollars(): string
    {
        return number_format(self::dollars(), 2, '.', '');
    }

    /**
     * True when a stored floor exists and differs from current shop suggestion.
     */
    public static function needsReview(?int $storedFloorCents): bool
    {
        if ($storedFloorCents === null) {
            return false;
        }

        return $storedFloorCents !== self::amountCents();
    }
}
