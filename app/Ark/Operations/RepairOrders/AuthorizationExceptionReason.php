<?php

namespace App\Ark\Operations\RepairOrders;

enum AuthorizationExceptionReason: string
{
    case Diagnostic = 'diagnostic';
    case Warranty = 'warranty';
    case Internal = 'internal';
    case Program = 'program';
    case Comeback = 'comeback';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Diagnostic => 'Diagnostic',
            self::Warranty => 'Warranty',
            self::Internal => 'Internal',
            self::Program => 'Program',
            self::Comeback => 'Comeback',
            self::Other => 'Other',
        };
    }
}
