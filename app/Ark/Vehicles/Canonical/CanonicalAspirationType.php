<?php

namespace App\Ark\Vehicles\Canonical;

enum CanonicalAspirationType: string
{
    case NaturallyAspirated = 'naturally_aspirated';
    case Turbocharged = 'turbocharged';
    case TwinTurbo = 'twin_turbo';
    case Supercharged = 'supercharged';

    public function label(): string
    {
        return match ($this) {
            self::NaturallyAspirated => 'Naturally Aspirated',
            self::Turbocharged => 'Turbocharged',
            self::TwinTurbo => 'Twin Turbo',
            self::Supercharged => 'Supercharged',
        };
    }
}
