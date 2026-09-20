<?php

namespace App\Ark\Vehicles;

use App\Ark\Vehicles\Canonical\CanonicalAspirationType;
use App\Ark\Vehicles\Canonical\CanonicalDrivetrain;
use App\Ark\Vehicles\Canonical\CanonicalFuelType;
use App\Ark\Vehicles\Canonical\CanonicalTransmission;

final readonly class CanonicalVehicleIdentity
{
    public function __construct(
        public ?string $vin,
        public ?string $normalizedVin,
        public ?int $year,
        public ?string $make,
        public ?string $model,
        public ?string $trim,
        public ?string $engineDisplay,
        public ?string $engineCode,
        public ?string $displacementLiters,
        public ?CanonicalFuelType $fuelType,
        public ?CanonicalAspirationType $aspiration,
        public ?CanonicalDrivetrain $drivetrain,
        public ?CanonicalTransmission $transmission,
        public ?string $bodyStyle,
        public ?string $manufacturer,
        public ?string $normalizedVehicleKey,
        public ?string $source,
    ) {}

    public function isUsable(): bool
    {
        return filled($this->make) && filled($this->model);
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toFormArray(): array
    {
        return [
            'vin' => $this->vin,
            'normalized_vin' => $this->normalizedVin,
            'year' => $this->year,
            'make' => $this->make,
            'model' => $this->model,
            'trim' => $this->trim,
            'engine' => $this->engineDisplay,
            'engine_display' => $this->engineDisplay,
            'engine_code' => $this->engineCode,
            'displacement_liters' => $this->displacementLiters,
            'fuel_type' => $this->fuelType?->value,
            'aspiration' => $this->aspiration?->value,
            'drive' => $this->drivetrain?->label(),
            'drivetrain' => $this->drivetrain?->value,
            'transmission' => $this->transmission?->label(),
            'body_style' => $this->bodyStyle,
            'manufacturer' => $this->manufacturer,
            'normalized_vehicle_key' => $this->normalizedVehicleKey,
            'vehicle_identity_source' => $this->source,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toPersistenceArray(): array
    {
        return [
            'vin' => $this->vin,
            'normalized_vin' => $this->normalizedVin,
            'year' => $this->year,
            'make' => $this->make,
            'model' => $this->model,
            'trim' => $this->trim,
            'engine' => $this->engineDisplay,
            'engine_display' => $this->engineDisplay,
            'engine_code' => $this->engineCode,
            'displacement_liters' => $this->displacementLiters,
            'fuel_type' => $this->fuelType?->value,
            'aspiration' => $this->aspiration?->value,
            'drive' => $this->drivetrain?->label(),
            'drivetrain' => $this->drivetrain?->value,
            'transmission' => $this->transmission?->label(),
            'body_style' => $this->bodyStyle,
            'manufacturer' => $this->manufacturer,
            'normalized_vehicle_key' => $this->normalizedVehicleKey,
            'vehicle_identity_source' => $this->source,
            'vehicle_identity_built_at' => now(),
        ];
    }
}
