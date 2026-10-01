<?php

namespace App\Ark\Vehicles;

interface PlateDecoder
{
    public function decodePlate(string $plate, string $state): ?CanonicalVehicleIdentity;
}
