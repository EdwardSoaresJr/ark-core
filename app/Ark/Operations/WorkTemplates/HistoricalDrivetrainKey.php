<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Vehicles\VehicleText;

/**
 * Recall-safe drivetrain token for Historical Work Recall only.
 *
 * Does not use {@see \App\Ark\Vehicles\DrivetrainNormalizer}: that path maps
 * generic 2WD → FWD, which manufactures specificity Historical Recall must not.
 *
 * Tokens: fwd · rwd · awd · 4wd · 2wd (ambiguous) · null (unknown).
 */
final class HistoricalDrivetrainKey
{
    public const FWD = 'fwd';

    public const RWD = 'rwd';

    public const AWD = 'awd';

    public const FOUR = '4wd';

    public const TWO = '2wd';

    /**
     * @return self::FWD|self::RWD|self::AWD|self::FOUR|self::TWO|null
     */
    public static function fromVehicle(Vehicle $vehicle): ?string
    {
        foreach ([$vehicle->drivetrain, $vehicle->drive] as $raw) {
            $key = self::fromRaw($raw);
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return self::FWD|self::RWD|self::AWD|self::FOUR|self::TWO|null
     */
    public static function fromRaw(?string $value): ?string
    {
        $clean = VehicleText::clean($value);

        if ($clean === null) {
            return null;
        }

        $upper = strtoupper($clean);

        if (
            str_contains($upper, '4WD')
            || str_contains($upper, '4X4')
            || str_contains($upper, 'FOUR-WHEEL')
            || str_contains($upper, 'FOUR WHEEL')
        ) {
            return self::FOUR;
        }

        if (
            str_contains($upper, 'AWD')
            || str_contains($upper, 'ALL-WHEEL')
            || str_contains($upper, 'ALL WHEEL')
            || str_contains($upper, '4MATIC')
            || str_contains($upper, 'QUATTRO')
            || str_contains($upper, 'XDRIVE')
        ) {
            return self::AWD;
        }

        if (
            str_contains($upper, 'FWD')
            || str_contains($upper, 'FRONT-WHEEL')
            || str_contains($upper, 'FRONT WHEEL')
        ) {
            return self::FWD;
        }

        if (
            str_contains($upper, 'RWD')
            || str_contains($upper, 'REAR-WHEEL')
            || str_contains($upper, 'REAR WHEEL')
        ) {
            return self::RWD;
        }

        // Preserve ambiguity — do not invent FWD/RWD from 2WD / 4x2.
        if (
            $upper === '2WD'
            || $upper === '4X2'
            || $upper === '2-WHEEL'
            || $upper === '2 WHEEL'
            || str_contains($upper, 'TWO-WHEEL')
            || str_contains($upper, 'TWO WHEEL')
        ) {
            return self::TWO;
        }

        $lower = strtolower($clean);

        return match ($lower) {
            self::FWD, self::RWD, self::AWD, self::FOUR, self::TWO => $lower,
            default => null,
        };
    }

    /**
     * @return 'same'|'different'|'unknown'
     */
    public static function compare(?string $a, ?string $b): string
    {
        if ($a === null || $b === null) {
            return 'unknown';
        }

        return $a === $b ? 'same' : 'different';
    }

    public static function label(?string $key): string
    {
        return match ($key) {
            self::FWD => 'FWD',
            self::RWD => 'RWD',
            self::AWD => 'AWD',
            self::FOUR => '4WD',
            self::TWO => '2WD',
            default => 'Unknown',
        };
    }
}
