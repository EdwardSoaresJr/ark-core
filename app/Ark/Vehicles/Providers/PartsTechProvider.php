<?php

namespace App\Ark\Vehicles\Providers;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Vehicles\CanonicalVehicleIdentity;
use App\Ark\Vehicles\PlateDecoder;
use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VehicleNormalizer;
use App\Ark\Vehicles\VehicleText;
use App\Ark\Vehicles\VinDecoder;
use Illuminate\Support\Facades\Http;

final class PartsTechProvider implements VinDecoder, PlateDecoder
{
    public function __construct(
        private readonly VehicleNormalizer $normalizer,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function decode(string $vin): ?CanonicalVehicleIdentity
    {
        return $this->requestDecode(['vin' => $vin], $vin);
    }

    public function decodePlate(string $plate, string $state): ?CanonicalVehicleIdentity
    {
        $plate = strtoupper(trim($plate));
        $state = strtoupper(trim($state));

        if ($plate === '' || strlen($state) < 2) {
            return null;
        }

        return $this->requestDecode([
            'plate' => $plate,
            'state' => $state,
        ]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function requestDecode(array $query, ?string $fallbackVin = null): ?CanonicalVehicleIdentity
    {
        $baseUrl = $this->credentials->partsTechBaseUrl();
        $username = $this->credentials->partsTechUsername();
        $apiKey = $this->credentials->partsTechApiKey();

        if ($baseUrl === '' || ! $username || ! $apiKey) {
            return null;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-Api-Key' => (string) $apiKey,
            ])
            ->get($baseUrl.'/api/v2/vehicles/decode', [
                'username' => $username,
                ...$query,
            ]);

        if (! $response->ok()) {
            return null;
        }

        return $this->mapPayload($response->json(), $fallbackVin);
    }

    private function mapPayload(mixed $payload, ?string $fallbackVin = null): ?CanonicalVehicleIdentity
    {
        if (! is_array($payload)) {
            return null;
        }

        $vehicle = $payload['vehicle'] ?? $payload['data'] ?? $payload;

        if (! is_array($vehicle)) {
            return null;
        }

        $resolvedVin = filled($fallbackVin)
            ? $fallbackVin
            : (new \App\Ark\Vehicles\VinNormalizer)->coerceInput($vehicle['vin'] ?? null);

        return $this->normalizer->normalize(new RawVehicleIdentity(
            vin: $resolvedVin,
            year: VehicleText::clean($vehicle['year'] ?? $vehicle['model_year'] ?? null),
            make: VehicleText::clean($vehicle['make'] ?? null),
            model: VehicleText::clean($vehicle['model'] ?? null),
            trim: VehicleText::clean($vehicle['trim'] ?? $vehicle['submodel'] ?? null),
            engine: VehicleText::clean($vehicle['engine'] ?? null),
            engineCode: VehicleText::clean($vehicle['engine_code'] ?? null),
            displacementLiters: VehicleText::clean($vehicle['displacement_liters'] ?? $vehicle['displacement'] ?? null),
            fuelType: VehicleText::clean($vehicle['fuel_type'] ?? null),
            aspiration: VehicleText::clean($vehicle['aspiration'] ?? null),
            drivetrain: VehicleText::clean($vehicle['drive'] ?? $vehicle['drivetrain'] ?? $vehicle['drive_type'] ?? $vehicle['driveType'] ?? null),
            transmission: VehicleText::clean($vehicle['transmission'] ?? $vehicle['transmission_type'] ?? $vehicle['transmissionType'] ?? null),
            bodyStyle: VehicleText::clean($vehicle['body_style'] ?? null),
            manufacturer: VehicleText::clean($vehicle['manufacturer'] ?? null),
            source: 'partstech',
        ));
    }
}
