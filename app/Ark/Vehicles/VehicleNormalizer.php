<?php

namespace App\Ark\Vehicles;

use Illuminate\Support\Str;

final class VehicleNormalizer
{
    public function __construct(
        private readonly VinNormalizer $vinNormalizer = new VinNormalizer,
        private readonly EngineNormalizer $engineNormalizer = new EngineNormalizer,
        private readonly DrivetrainNormalizer $drivetrainNormalizer = new DrivetrainNormalizer,
        private readonly TransmissionNormalizer $transmissionNormalizer = new TransmissionNormalizer,
        private readonly FuelTypeNormalizer $fuelTypeNormalizer = new FuelTypeNormalizer,
        private readonly AspirationNormalizer $aspirationNormalizer = new AspirationNormalizer,
    ) {}

    public function normalize(RawVehicleIdentity $raw): CanonicalVehicleIdentity
    {
        $normalizedVin = $this->vinNormalizer->normalize($raw->vin);
        $year = $this->normalizeYear($raw->year);
        $engine = $this->engineNormalizer->normalize($raw->engine, $raw->engineCode, $raw->displacementLiters);
        $drivetrain = $this->drivetrainNormalizer->normalize($raw->drivetrain);
        $transmission = $this->transmissionNormalizer->normalize($raw->transmission, $raw->trim);
        $fuelType = $this->fuelTypeNormalizer->normalize($raw->fuelType);
        $aspiration = $this->aspirationNormalizer->normalize($raw->aspiration, $raw->engine);

        $identity = new CanonicalVehicleIdentity(
            vin: $normalizedVin,
            normalizedVin: $normalizedVin,
            year: $year,
            make: VehicleText::displayMake($raw->make),
            model: VehicleText::displayModel($raw->model),
            trim: VehicleText::displayTrim($raw->trim),
            engineDisplay: $engine->display,
            engineCode: $engine->code,
            displacementLiters: $engine->displacementLiters,
            fuelType: $fuelType,
            aspiration: $aspiration,
            drivetrain: $drivetrain,
            transmission: $transmission,
            bodyStyle: VehicleText::clean($raw->bodyStyle),
            manufacturer: VehicleText::clean($raw->manufacturer),
            normalizedVehicleKey: null,
            source: $raw->source,
        );

        return new CanonicalVehicleIdentity(
            vin: $identity->vin,
            normalizedVin: $identity->normalizedVin,
            year: $identity->year,
            make: $identity->make,
            model: $identity->model,
            trim: $identity->trim,
            engineDisplay: $identity->engineDisplay,
            engineCode: $identity->engineCode,
            displacementLiters: $identity->displacementLiters,
            fuelType: $identity->fuelType,
            aspiration: $identity->aspiration,
            drivetrain: $identity->drivetrain,
            transmission: $identity->transmission,
            bodyStyle: $identity->bodyStyle,
            manufacturer: $identity->manufacturer,
            normalizedVehicleKey: $this->buildKey($identity),
            source: $identity->source,
        );
    }

    public function merge(CanonicalVehicleIdentity $primary, CanonicalVehicleIdentity $secondary): CanonicalVehicleIdentity
    {
        $merged = new CanonicalVehicleIdentity(
            vin: $primary->vin ?? $secondary->vin,
            normalizedVin: $primary->normalizedVin ?? $secondary->normalizedVin,
            year: $primary->year ?? $secondary->year,
            make: $primary->make ?? $secondary->make,
            model: $primary->model ?? $secondary->model,
            trim: $primary->trim ?? $secondary->trim,
            engineDisplay: $primary->engineDisplay ?? $secondary->engineDisplay,
            engineCode: $primary->engineCode ?? $secondary->engineCode,
            displacementLiters: $primary->displacementLiters ?? $secondary->displacementLiters,
            fuelType: $primary->fuelType ?? $secondary->fuelType,
            aspiration: $primary->aspiration ?? $secondary->aspiration,
            drivetrain: $primary->drivetrain ?? $secondary->drivetrain,
            transmission: $primary->transmission ?? $secondary->transmission,
            bodyStyle: $primary->bodyStyle ?? $secondary->bodyStyle,
            manufacturer: $primary->manufacturer ?? $secondary->manufacturer,
            normalizedVehicleKey: null,
            source: $this->mergeSource($primary->source, $secondary->source),
        );

        return new CanonicalVehicleIdentity(
            vin: $merged->vin,
            normalizedVin: $merged->normalizedVin,
            year: $merged->year,
            make: $merged->make,
            model: $merged->model,
            trim: $merged->trim,
            engineDisplay: $merged->engineDisplay,
            engineCode: $merged->engineCode,
            displacementLiters: $merged->displacementLiters,
            fuelType: $merged->fuelType,
            aspiration: $merged->aspiration,
            drivetrain: $merged->drivetrain,
            transmission: $merged->transmission,
            bodyStyle: $merged->bodyStyle,
            manufacturer: $merged->manufacturer,
            normalizedVehicleKey: $this->buildKey($merged),
            source: $merged->source,
        );
    }

    private function mergeSource(?string $primary, ?string $secondary): ?string
    {
        if ($primary === null || $primary === $secondary) {
            return $primary ?? $secondary;
        }

        if ($secondary === null) {
            return $primary;
        }

        return $primary.'+'.$secondary;
    }

    private function normalizeYear(?string $year): ?int
    {
        $year = VehicleText::clean($year);

        if ($year === null || preg_match('/^\d{4}$/', $year) !== 1) {
            return null;
        }

        return (int) $year;
    }

    private function buildKey(CanonicalVehicleIdentity $identity): ?string
    {
        if (! filled($identity->make) || ! filled($identity->model)) {
            return null;
        }

        $parts = array_filter([
            $identity->year,
            $identity->make,
            $identity->model,
            $identity->trim,
            $identity->displacementLiters === null ? null : $identity->displacementLiters.'l',
            $identity->engineCode,
            $identity->drivetrain?->value,
            $identity->transmission?->value,
        ], fn ($part): bool => filled($part));

        return Str::of(implode(' ', $parts))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }
}
