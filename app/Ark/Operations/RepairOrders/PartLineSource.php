<?php

namespace App\Ark\Operations\RepairOrders;

enum PartLineSource: string
{
    case ShopSupplied = 'shop_supplied';
    case CustomerSupplied = 'customer_supplied';

    public function label(): string
    {
        return match ($this) {
            self::ShopSupplied => 'Shop supplied',
            self::CustomerSupplied => 'Customer supplied',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::ShopSupplied;
    }
}
