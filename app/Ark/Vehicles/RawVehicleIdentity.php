<?php

namespace App\Ark\Vehicles;

final readonly class RawVehicleIdentity
{
    public function __construct(
        public ?string $vin = null,
        public ?string $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?string $trim = null,
        public ?string $engine = null,
        public ?string $engineCode = null,
        public ?string $displacementLiters = null,
        public ?string $fuelType = null,
        public ?string $aspiration = null,
        public ?string $drivetrain = null,
        public ?string $transmission = null,
        public ?string $bodyStyle = null,
        public ?string $manufacturer = null,
        public ?string $source = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vin: self::vinOrNull($data['vin'] ?? $data['normalized_vin'] ?? null),
            year: self::stringOrNull($data['year'] ?? null),
            make: self::stringOrNull($data['make'] ?? null),
            model: self::stringOrNull($data['model'] ?? null),
            trim: self::stringOrNull($data['trim'] ?? null),
            engine: self::stringOrNull($data['engine'] ?? $data['engine_display'] ?? null),
            engineCode: self::stringOrNull($data['engine_code'] ?? null),
            displacementLiters: self::stringOrNull($data['displacement_liters'] ?? null),
            fuelType: self::stringOrNull($data['fuel_type'] ?? null),
            aspiration: self::stringOrNull($data['aspiration'] ?? null),
            drivetrain: self::stringOrNull($data['drivetrain'] ?? $data['drive'] ?? null),
            transmission: self::stringOrNull($data['transmission'] ?? null),
            bodyStyle: self::stringOrNull($data['body_style'] ?? null),
            manufacturer: self::stringOrNull($data['manufacturer'] ?? null),
            source: self::stringOrNull($data['source'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function vinOrNull(mixed $value): ?string
    {
        return (new VinNormalizer)->coerceInput($value);
    }
}
