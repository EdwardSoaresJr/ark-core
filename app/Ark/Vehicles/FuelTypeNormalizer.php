<?php

namespace App\Ark\Vehicles;

use App\Ark\Vehicles\Canonical\CanonicalFuelType;

final class FuelTypeNormalizer
{
    public function normalize(?string $value): ?CanonicalFuelType
    {
        $value = VehicleText::clean($value);

        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);

        if (str_contains($upper, 'DIESEL')) {
            return CanonicalFuelType::Diesel;
        }

        if (str_contains($upper, 'ELECTRIC') || $upper === 'EV') {
            return CanonicalFuelType::Electric;
        }

        if (str_contains($upper, 'HYBRID') || str_contains($upper, 'PHEV')) {
            return CanonicalFuelType::Hybrid;
        }

        if (str_contains($upper, 'FLEX') || str_contains($upper, 'E85')) {
            return CanonicalFuelType::FlexFuel;
        }

        if (str_contains($upper, 'GAS') || str_contains($upper, 'PETROL')) {
            return CanonicalFuelType::Gasoline;
        }

        return CanonicalFuelType::tryFrom(strtolower($value));
    }
}
