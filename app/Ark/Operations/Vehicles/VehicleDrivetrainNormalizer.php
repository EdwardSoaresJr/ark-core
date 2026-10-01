<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Vehicles\DrivetrainNormalizer;
use App\Ark\Vehicles\TransmissionNormalizer;

final class VehicleDrivetrainNormalizer
{
    public static function normalizeDrive(?string $value): ?string
    {
        return (new DrivetrainNormalizer)->normalize($value)?->label();
    }

    public static function normalizeTransmission(?string $value): ?string
    {
        return (new TransmissionNormalizer)->normalize($value)?->label();
    }

    public static function inferTransmissionFromText(?string ...$parts): ?string
    {
        return (new TransmissionNormalizer)->normalize(...$parts)?->label();
    }

    /**
     * @return list<string>
     */
    public static function transmissionSelectValues(): array
    {
        return ['Automatic', 'Manual'];
    }

    /**
     * @return list<string>
     */
    public static function driveSelectValues(): array
    {
        return ['FWD', 'AWD', '4WD/4-Wheel Drive/4x4', 'RWD'];
    }
}
