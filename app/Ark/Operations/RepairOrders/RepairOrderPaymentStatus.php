<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairOrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Balance due',
            self::Paid => 'Paid',
        };
    }

    public function handoffLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Collect balance before release',
            self::Paid => 'Ready to release',
        };
    }
}
