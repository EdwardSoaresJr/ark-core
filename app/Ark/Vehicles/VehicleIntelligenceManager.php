<?php

namespace App\Ark\Vehicles;

use Illuminate\Support\Facades\Log;

final class VehicleIntelligenceManager
{
    /**
     * @param  iterable<VinDecoder>  $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly ?PlateDecoder $plateDecoder = null,
        private readonly VehicleNormalizer $normalizer = new VehicleNormalizer,
        private readonly VinNormalizer $vinNormalizer = new VinNormalizer,
    ) {}

    public function decodeVin(string $vin): ?CanonicalVehicleIdentity
    {
        $vin = $this->vinNormalizer->normalize($vin);

        if ($vin === null || strlen($vin) < 11) {
            return null;
        }

        $identity = null;

        foreach ($this->providers as $provider) {
            $candidate = $provider->decode($vin);
            $usable = (bool) $candidate?->isUsable();

            Log::debug('vin_decode.provider', [
                'provider' => class_basename($provider),
                'usable' => $usable,
                'source' => $candidate?->source,
            ]);

            if (! $usable) {
                continue;
            }

            $identity = $identity === null
                ? $candidate
                : $this->normalizer->merge($identity, $candidate);
        }

        return $identity;
    }

    public function decodePlate(string $plate, string $state): ?CanonicalVehicleIdentity
    {
        $plate = strtoupper(trim($plate));
        $state = strtoupper(trim($state));

        if ($plate === '' || strlen($state) < 2 || ! $this->plateDecoder instanceof PlateDecoder) {
            return null;
        }

        $identity = $this->plateDecoder->decodePlate($plate, $state);

        if ($identity === null || ! $identity->isUsable()) {
            return null;
        }

        if (filled($identity->normalizedVin)) {
            return $this->decodeVin($identity->normalizedVin) ?? $identity;
        }

        return $identity;
    }
}
