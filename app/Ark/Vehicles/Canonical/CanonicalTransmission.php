<?php

namespace App\Ark\Vehicles\Canonical;

enum CanonicalTransmission: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Cvt = 'cvt';
    case Dct = 'dct';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
            self::Cvt => 'CVT',
            self::Dct => 'DCT',
        };
    }
}
