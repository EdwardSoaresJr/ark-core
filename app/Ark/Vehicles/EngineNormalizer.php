<?php

namespace App\Ark\Vehicles;

final class EngineNormalizer
{
    public function normalize(?string $engine, ?string $engineCode = null, ?string $displacementLiters = null): NormalizedEngine
    {
        $engine = VehicleText::clean($engine);
        $engineCode = VehicleText::clean($engineCode);
        $displacement = $this->normalizeDisplacement($displacementLiters) ?? $this->extractDisplacement($engine);

        $display = $engine;

        if ($display === null && $displacement !== null) {
            $display = $displacement.'L';
        }

        if ($display !== null && $displacement !== null && preg_match('/^\d+(?:\.\d+)?$/', $display) === 1) {
            $display = $displacement.'L';
        }

        return new NormalizedEngine(
            display: $display,
            code: $engineCode,
            displacementLiters: $displacement,
        );
    }

    private function extractDisplacement(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*L?/i', $value, $matches) !== 1) {
            return null;
        }

        return $this->normalizeDisplacement($matches[1]);
    }

    private function normalizeDisplacement(?string $value): ?string
    {
        $value = VehicleText::clean($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)/', $value, $matches) !== 1) {
            return null;
        }

        $number = rtrim(rtrim($matches[1], '0'), '.');

        return $number === '' ? null : $number;
    }
}
