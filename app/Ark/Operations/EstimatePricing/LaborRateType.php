<?php

namespace App\Ark\Operations\EstimatePricing;

enum LaborRateType: string
{
    case Hourly = 'hourly';
    case Zero = 'zero';
    case Contract = 'contract';
    case Cost = 'cost';

    public function resolvesToHourlyCents(): bool
    {
        return match ($this) {
            self::Hourly, self::Contract, self::Zero => true,
            self::Cost => false,
        };
    }
}
