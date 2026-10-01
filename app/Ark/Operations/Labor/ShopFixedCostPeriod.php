<?php

namespace App\Ark\Operations\Labor;

final class ShopFixedCostPeriod
{
    public const WEEKLY = 'weekly';

    public const BIWEEKLY = 'biweekly';

    public const MONTHLY = 'monthly';

    public const ANNUAL = 'annual';

    /** @var list<string> */
    public const ALL = [
        self::WEEKLY,
        self::BIWEEKLY,
        self::MONTHLY,
        self::ANNUAL,
    ];

    public static function toMonthly(float $amount, string $period): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return round(match (self::normalize($period)) {
            self::WEEKLY => $amount * 52 / 12,
            self::BIWEEKLY => $amount * 26 / 12,
            self::ANNUAL => $amount / 12,
            default => $amount,
        }, 2);
    }

    public static function normalize(string $period): string
    {
        return in_array($period, self::ALL, true) ? $period : self::MONTHLY;
    }

    public static function label(string $period): string
    {
        return match (self::normalize($period)) {
            self::WEEKLY => 'Weekly',
            self::BIWEEKLY => 'Biweekly',
            self::ANNUAL => 'Annual',
            default => 'Monthly',
        };
    }
}
