<?php

namespace App\Ark\Operations\Financial;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Check = 'check';
    case Eft = 'eft';
    case Financing = 'financing';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'External Card',
            self::Check => 'Check',
            self::Eft => 'EFT',
            self::Financing => 'Financing',
        };
    }

    public static function stored(self|string $method): string
    {
        return $method instanceof self ? $method->value : $method;
    }

    public static function isCash(self|string $method): bool
    {
        return self::stored($method) === self::Cash->value;
    }
}
