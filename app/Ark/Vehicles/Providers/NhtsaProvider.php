<?php

namespace App\Ark\Vehicles\Providers;

use App\Ark\Vehicles\CanonicalVehicleIdentity;
use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VehicleNormalizer;
use App\Ark\Vehicles\VehicleText;
use App\Ark\Vehicles\VinDecoder;
use Illuminate\Support\Facades\Http;

final class NhtsaProvider implements VinDecoder
{
    public function __construct(private readonly VehicleNormalizer $normalizer) {}

    public function decode(string $vin): ?CanonicalVehicleIdentity
    {
        $response = Http::timeout(15)->get(
            "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValuesExtended/{$vin}",
            ['format' => 'json'],
        );

        if (! $response->ok()) {
            return null;
        }

        $result = $response->json('Results.0');

        if (! is_array($result)) {
            return null;
        }

        $transmissionRaw = $this->transmissionFromResult($result);
        $trim = VehicleText::clean($result['Trim'] ?? null) ?? VehicleText::clean($result['Series'] ?? null);
        $engine = VehicleText::clean($result['EngineModel'] ?? null)
            ?? $this->engineFromDisplacement($result['DisplacementL'] ?? null);

        return $this->normalizer->normalize(new RawVehicleIdentity(
            vin: $vin,
            year: VehicleText::clean(isset($result['ModelYear']) ? (string) $result['ModelYear'] : null),
            make: VehicleText::clean($result['Make'] ?? null),
            model: VehicleText::clean($result['Model'] ?? null),
            trim: $trim,
            engine: $engine,
            displacementLiters: VehicleText::clean($result['DisplacementL'] ?? null),
            fuelType: VehicleText::clean($result['FuelTypePrimary'] ?? null),
            aspiration: VehicleText::clean($result['EngineConfiguration'] ?? null),
            drivetrain: VehicleText::clean($result['DriveType'] ?? null),
            transmission: $transmissionRaw,
            bodyStyle: VehicleText::clean($result['BodyClass'] ?? null),
            manufacturer: VehicleText::clean($result['Manufacturer'] ?? $result['ManufacturerName'] ?? null),
            source: 'nhtsa',
        ));
    }

    private function engineFromDisplacement(mixed $value): ?string
    {
        $value = VehicleText::clean($value);

        return $value === null ? null : $value.'L';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function transmissionFromResult(array $result): ?string
    {
        $style = VehicleText::clean($result['TransmissionStyle'] ?? null);
        $speeds = VehicleText::clean($result['TransmissionSpeeds'] ?? null);

        if ($style !== null && $speeds !== null && preg_match('/^\d+$/', $speeds) === 1) {
            return "{$speeds}-Speed {$style}";
        }

        return $style ?? $speeds;
    }
}
