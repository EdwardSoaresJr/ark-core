<?php

namespace App\Ark\Operations\EstimatePricing;

enum LaborBillingMinimumMode: string
{
    case None = 'none';
    case PerLine = 'per_line';
    case PerConcern = 'per_concern';
    case PerRepairOrder = 'per_ro';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No minimum',
            self::PerLine => 'Minimum per labor line',
            self::PerConcern => 'Minimum per concern',
            self::PerRepairOrder => 'Minimum per repair order',
        };
    }
}
