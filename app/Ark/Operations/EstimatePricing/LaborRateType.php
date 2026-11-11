<?php

namespace App\Ark\Operations\EstimatePricing;

enum LaborRateType: string
{
    /**
     * Legacy shop / migration value. Existing tenants still store `standard` on
     * labor profiles; removing this case breaks estimate pricing for those shops.
     */
    case Standard = 'standard';
    case Hourly = 'hourly';
    case Zero = 'zero';
    case Contract = 'contract';
    case Cost = 'cost';

    public function resolvesToHourlyCents(): bool
    {
        return match ($this) {
            self::Standard, self::Hourly, self::Contract, self::Zero => true,
            self::Cost => false,
        };
    }
}
