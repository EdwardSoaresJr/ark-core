<?php

namespace App\Ark\Vehicles;

final class VinNormalizer
{
    public function normalize(?string $vin): ?string
    {
        if ($vin === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $vin) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Coerce request/JSON payloads to a VIN string without losing trailing zeros.
     */
    public function coerceInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return null;
        }

        if (is_int($value)) {
            return $this->normalize((string) $value);
        }

        if (is_float($value)) {
            return $this->normalize(sprintf('%.0f', $value));
        }

        return $this->normalize(trim((string) $value));
    }
}
