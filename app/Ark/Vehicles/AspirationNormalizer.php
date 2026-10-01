<?php

namespace App\Ark\Vehicles;

use App\Ark\Vehicles\Canonical\CanonicalAspirationType;

final class AspirationNormalizer
{
    public function normalize(?string ...$values): ?CanonicalAspirationType
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeOne($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeOne(?string $value): ?CanonicalAspirationType
    {
        $value = VehicleText::clean($value);

        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);

        if (
            str_contains($upper, 'TWIN TURBO')
            || str_contains($upper, 'BI-TURBO')
            || str_contains($upper, 'BITURBO')
            || preg_match('/\bTT\b/', $upper) === 1
        ) {
            return CanonicalAspirationType::TwinTurbo;
        }

        if (str_contains($upper, 'TURBO')) {
            return CanonicalAspirationType::Turbocharged;
        }

        if (str_contains($upper, 'SUPERCHARG')) {
            return CanonicalAspirationType::Supercharged;
        }

        if (str_contains($upper, 'NATURALLY ASPIRATED') || $upper === 'NA' || $upper === 'N/A') {
            return CanonicalAspirationType::NaturallyAspirated;
        }

        return CanonicalAspirationType::tryFrom(strtolower($value));
    }
}
