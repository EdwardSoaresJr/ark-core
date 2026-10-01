<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\TelephonyExtension;
use InvalidArgumentException;

final class SuggestedWorkstationExtension
{
    public static function next(): string
    {
        $used = TelephonyExtension::query()
            ->whereNotNull('workstation_id')
            ->pluck('extension')
            ->map(fn (mixed $value): int => (int) preg_replace('/\D/', '', (string) $value))
            ->filter(fn (int $value): bool => $value >= 101 && $value <= 199)
            ->unique()
            ->sort()
            ->values();

        for ($candidate = 101; $candidate <= 199; $candidate++) {
            if (! $used->contains($candidate)) {
                return (string) $candidate;
            }
        }

        throw new InvalidArgumentException('No desk phone extensions available in the 101–199 range.');
    }
}
