<?php

namespace App\Ark\Operations\EstimatePricing;

enum LaborRateType: string
{
    case Hourly = 'hourly';
    case Zero = 'zero';
    case Contract = 'contract';
    case Cost = 'cost';
    /**
     * Follows the shop Standard hourly rate from ShopSettings at resolve time when
     * the policy row has no stamped cents. Existing tenants still store `standard`
     * on labor profiles; this case must remain for compatibility.
     */
    case Standard = 'standard';

    public function resolvesToHourlyCents(): bool
    {
        return match ($this) {
            self::Hourly, self::Contract, self::Zero, self::Standard => true,
            self::Cost => false,
        };
    }

    public function followsShopStandard(): bool
    {
        return $this === self::Standard;
    }

    public function settingsMode(): string
    {
        return match ($this) {
            self::Standard => 'standard',
            self::Zero => 'zero',
            default => 'custom',
        };
    }
}
