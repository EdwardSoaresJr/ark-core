<?php

namespace App\Ark\Vehicles\Canonical;

enum CanonicalDrivetrain: string
{
    case Fwd = 'fwd';
    case Rwd = 'rwd';
    case Awd = 'awd';
    case FourWheelDrive = '4wd';

    public function label(): string
    {
        return match ($this) {
            self::Fwd => 'FWD',
            self::Rwd => 'RWD',
            self::Awd => 'AWD',
            self::FourWheelDrive => '4WD/4-Wheel Drive/4x4',
        };
    }
}
