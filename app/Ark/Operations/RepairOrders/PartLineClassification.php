<?php

namespace App\Ark\Operations\RepairOrders;

enum PartLineClassification: string
{
    case Oem = 'oem';
    case AftermarketReplacement = 'aftermarket_replacement';
    case PerformanceCustom = 'performance_custom';

    public function label(): string
    {
        return match ($this) {
            self::Oem => 'OEM',
            self::AftermarketReplacement => 'Aftermarket replacement',
            self::PerformanceCustom => 'Performance / custom',
        };
    }

    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
