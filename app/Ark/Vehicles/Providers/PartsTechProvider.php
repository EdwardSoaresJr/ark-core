<?php

namespace App\Ark\Vehicles\Providers;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Vehicles\CanonicalVehicleIdentity;
use App\Ark\Vehicles\PartsTechDecodeException;
use App\Ark\Vehicles\PlateDecoder;
use App\Ark\Vehicles\VehicleNormalizer;
use App\Ark\Vehicles\VinDecoder;

/**
 * Stock Core does not call PartsTech. The catalog client lives in private SaaS.
 */
final class PartsTechProvider implements VinDecoder, PlateDecoder
{
    public function __construct(
        private readonly VehicleNormalizer $normalizer,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function decode(string $vin): ?CanonicalVehicleIdentity
    {
        return null;
    }

    public function decodePlate(string $plate, string $state): ?CanonicalVehicleIdentity
    {
        throw new PartsTechDecodeException('PartsTech is not part of this shop installation.');
    }
}
