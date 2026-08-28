<?php

namespace App\Ark\Vehicles;

use App\Ark\Vehicles\Canonical\CanonicalTransmission;

final class TransmissionNormalizer
{
    public function normalize(?string ...$values): ?CanonicalTransmission
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeOne($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeOne(?string $value): ?CanonicalTransmission
    {
        $value = VehicleText::clean($value);

        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);

        if (
            str_contains($upper, 'CVT')
            || str_contains($upper, 'CONTINUOUSLY VARIABLE')
            || str_contains($upper, 'E-CVT')
        ) {
            return CanonicalTransmission::Cvt;
        }

        if (
            str_contains($upper, 'DUAL CLUTCH')
            || str_contains($upper, 'DCT')
            || str_contains($upper, 'DSG')
            || str_contains($upper, 'PDK')
        ) {
            return CanonicalTransmission::Dct;
        }

        if (
            str_contains($upper, 'MANUAL')
            || str_contains($upper, 'STANDARD')
            || str_contains($upper, 'M/T')
            || $upper === 'MT'
        ) {
            return CanonicalTransmission::Manual;
        }

        if (
            $upper === 'AUTO'
            || str_starts_with($upper, 'AUTO ')
            || str_starts_with($upper, 'AUTO/')
            || str_contains($upper, 'AUTO')
            || str_contains($upper, 'A/T')
            || str_contains($upper, 'TIPTRONIC')
            || str_contains($upper, 'DIRECT DRIVE')
            || str_contains($upper, 'SINGLE SPEED')
            || str_contains($upper, 'SEMI-AUTOMATIC')
            || str_contains($upper, 'AUTOMATED MANUAL')
            || str_contains($upper, ' TRANS')
        ) {
            return CanonicalTransmission::Automatic;
        }

        return CanonicalTransmission::tryFrom(strtolower($value));
    }
}
