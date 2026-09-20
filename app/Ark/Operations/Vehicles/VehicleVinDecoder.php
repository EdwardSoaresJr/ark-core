<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Vehicles\VehicleIntelligenceManager;

class VehicleVinDecoder
{
    public function __construct(private readonly VehicleIntelligenceManager $manager) {}

    /**
     * @return array<string, string|null>|null
     *
     * @deprecated Use VehicleIntelligenceManager directly for new vehicle intelligence work.
     */
    public function decode(string $vin): ?array
    {
        return $this->manager->decodeVin($vin)?->toFormArray();
    }
}
