<?php

namespace App\Ark\Vehicles\Providers;

use App\Ark\Vehicles\CanonicalVehicleIdentity;
use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VehicleNormalizer;

final class ManualEntryProvider
{
    public function __construct(private readonly VehicleNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function normalize(array $data): CanonicalVehicleIdentity
    {
        return $this->normalizer->normalize(RawVehicleIdentity::fromArray([
            ...$data,
            'source' => 'manual',
        ]));
    }
}
