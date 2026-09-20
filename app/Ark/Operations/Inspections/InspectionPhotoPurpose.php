<?php

namespace App\Ark\Operations\Inspections;

enum InspectionPhotoPurpose: string
{
    case Internal = 'internal';
    case Customer = 'customer';
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Customer => 'Customer view',
            self::Before => 'Before',
            self::After => 'After',
        };
    }

    /**
     * Explicit customer-facing allowlist. New enum cases must be classified here.
     */
    public function isCustomerFacing(): bool
    {
        return match ($this) {
            self::Customer, self::Before, self::After => true,
            self::Internal => false,
        };
    }
}
