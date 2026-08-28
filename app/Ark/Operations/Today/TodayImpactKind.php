<?php

namespace App\Ark\Operations\Today;

enum TodayImpactKind: string
{
    case Revenue = 'revenue';
    case CustomerTrust = 'customer_trust';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'Revenue',
            self::CustomerTrust => 'Customer trust',
            self::Production => 'Production',
        };
    }
}
