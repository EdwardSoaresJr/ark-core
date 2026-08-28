<?php

namespace App\Ark\Operations\RepairOrders;

enum PartLineWarrantyImpact: string
{
    case None = 'none';
    case CustomerSupplied = 'customer_supplied';
    case AftermarketRelated = 'aftermarket_related';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::CustomerSupplied => 'Customer supplied',
            self::AftermarketRelated => 'Aftermarket related',
            self::Excluded => 'Excluded',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }
}
