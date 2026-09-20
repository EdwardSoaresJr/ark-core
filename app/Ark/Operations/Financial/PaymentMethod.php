<?php

namespace App\Ark\Operations\Financial;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Check = 'check';
    case Financing = 'financing';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card (external)',
            self::Check => 'Check',
            self::Financing => 'Financing',
        };
    }
}
