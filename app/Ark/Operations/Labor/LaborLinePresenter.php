<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\RepairOrders\RepairOrderLine;

final class LaborLinePresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forLine(RepairOrderLine $line): ?array
    {
        if ($line->type->value !== 'labor' || $line->labor_category_key === null) {
            return null;
        }

        $adjustment = LaborAdjustment::tryFrom((string) $line->labor_adjustment) ?? LaborAdjustment::Normal;
        $factor = (float) ($line->labor_adjustment_factor ?? $adjustment->defaultFactor());

        return [
            'book_hours' => (string) $line->labor_entered_hours,
            'category_key' => $line->labor_category_key,
            'category_name' => $line->labor_category_name,
            'adjustment' => $adjustment->value,
            'adjustment_label' => $adjustment->displayLabel($factor),
            'adjustment_factor' => (string) $line->labor_adjustment_factor,
            'reason' => $line->labor_adjustment_reason,
            'calculated_hours' => (string) $line->labor_billed_hours,
            'final_hours' => (string) $line->quantity,
            'rate_cents' => (int) ($line->labor_rate_cents ?? $line->unit_price_cents),
            'hours_overridden' => (bool) $line->labor_hours_overridden,
            'override_reason' => $line->labor_override_reason,
            'minimum_applied' => (bool) $line->labor_minimum_applied,
            'minimum_message' => self::minimumMessage($line),
            'summary' => self::summary($line, $adjustment, $factor),
        ];
    }

    private static function minimumMessage(RepairOrderLine $line): ?string
    {
        if (! $line->labor_minimum_applied || $line->labor_category_name === null) {
            return null;
        }

        $entered = number_format((float) $line->labor_entered_hours, 2, '.', '');
        $billed = number_format((float) $line->labor_billed_hours, 2, '.', '');

        return "{$line->labor_category_name} minimum applied: {$entered} hr → {$billed} hr";
    }

    private static function summary(RepairOrderLine $line, LaborAdjustment $adjustment, float $factor): string
    {
        $parts = [
            'Book '.$line->labor_entered_hours.' hr',
            $line->labor_category_name,
        ];

        if ($adjustment !== LaborAdjustment::Normal) {
            $parts[] = $adjustment->displayLabel($factor);
        }

        $finalHours = (string) $line->quantity;

        if ($line->labor_hours_overridden) {
            $parts[] = 'Final '.$finalHours.' hr (override)';

            if (filled($line->labor_override_reason)) {
                $parts[] = $line->labor_override_reason;
            }
        } else {
            $parts[] = 'Final '.$finalHours.' hr';
        }

        return implode(' · ', $parts);
    }
}
