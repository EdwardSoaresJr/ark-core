<?php

namespace App\Ark\Operations\Financial;

/**
 * Which living financial contract Financial Position is projecting from.
 * Never LivingInvoice / Compatibility — those are implementation debt, not business language.
 */
enum FinancialContractSource: string
{
    case Estimate = 'estimate';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Estimate => 'Estimate',
            self::Invoice => 'Invoice',
        };
    }
}
