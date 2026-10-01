<?php

namespace App\Ark\Vehicles;

use App\Ark\Vehicles\Canonical\CanonicalDrivetrain;

final class DrivetrainNormalizer
{
    public function normalize(?string $value): ?CanonicalDrivetrain
    {
        $value = VehicleText::clean($value);

        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);

        if (
            str_contains($upper, '4WD')
            || str_contains($upper, '4X4')
            || str_contains($upper, 'FOUR-WHEEL')
            || str_contains($upper, 'FOUR WHEEL')
        ) {
            return CanonicalDrivetrain::FourWheelDrive;
        }

        if (
            str_contains($upper, 'AWD')
            || str_contains($upper, 'ALL-WHEEL')
            || str_contains($upper, 'ALL WHEEL')
            || str_contains($upper, '4MATIC')
            || str_contains($upper, 'QUATTRO')
            || str_contains($upper, 'XDRIVE')
        ) {
            return CanonicalDrivetrain::Awd;
        }

        if (
            str_contains($upper, 'FWD')
            || str_contains($upper, 'FRONT-WHEEL')
            || str_contains($upper, 'FRONT WHEEL')
            || $upper === '4X2'
            || $upper === '2WD'
        ) {
            return CanonicalDrivetrain::Fwd;
        }

        if (str_contains($upper, 'RWD') || str_contains($upper, 'REAR-WHEEL') || str_contains($upper, 'REAR WHEEL')) {
            return CanonicalDrivetrain::Rwd;
        }

        return CanonicalDrivetrain::tryFrom(strtolower($value));
    }
}
