<?php

namespace App\Ark\Operations\Financial;

enum FinancialDocumentType: string
{
    case Estimate = 'estimate';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Estimate => 'Estimate',
            self::Invoice => 'Final Invoice',
        };
    }
}
