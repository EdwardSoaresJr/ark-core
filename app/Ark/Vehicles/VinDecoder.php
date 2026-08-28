<?php

namespace App\Ark\Vehicles;

interface VinDecoder
{
    public function decode(string $vin): ?CanonicalVehicleIdentity;
}
