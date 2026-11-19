<?php

namespace App\Ark\Operations\EstimatePricing;

enum BillingClassWarrantyMode: string
{
    case InheritShop = 'inherit_shop';
    case ClassSpecific = 'class_specific';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::InheritShop => 'Inherit shop warranty',
            self::ClassSpecific => 'Class-specific warranty',
            self::None => 'No warranty',
        };
    }

    public static function tryFromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(trim((string) $value)) ?? self::InheritShop;
    }
}
