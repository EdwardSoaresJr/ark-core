<?php

namespace App\Ark\Operations\Commitments;

enum CommitmentType: string
{
    case CustomerUpdate = 'customer_update';

    public function label(): string
    {
        return match ($this) {
            self::CustomerUpdate => 'Customer Update',
        };
    }

    public function titleFor(string $customerName): string
    {
        return match ($this) {
            self::CustomerUpdate => 'Update '.$customerName,
        };
    }
}
