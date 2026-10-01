<?php

namespace App\Ark\Operations\LaborGuides\Rte;

/**
 * RTE lab_id vehicle segment: 4-char job code + 4-char vehicle segment + suffix.
 */
final class RteLabVehicleSegment
{
    public const JOB_CODE_LENGTH = 4;

    public const SEGMENT_LENGTH = 4;

    public static function fromLabId(string $labId): ?string
    {
        if (strlen($labId) < self::JOB_CODE_LENGTH + self::SEGMENT_LENGTH) {
            return null;
        }

        return strtoupper(substr($labId, self::JOB_CODE_LENGTH, self::SEGMENT_LENGTH));
    }

    /**
     * @return list<string>
     */
    public static function segmentValuesForCar(string $carIdCode): array
    {
        $carIdCode = strtoupper(trim($carIdCode));

        if ($carIdCode === '') {
            return [];
        }

        $values = [$carIdCode];

        if (strlen($carIdCode) >= 2) {
            $values[] = substr($carIdCode, 0, 2).'xx';
        }

        if (strlen($carIdCode) >= 1) {
            $values[] = substr($carIdCode, 0, 1).'xxx';
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<string>
     */
    public static function labIdPatternsForCar(string $carIdCode): array
    {
        $carIdCode = strtoupper(trim($carIdCode));

        if ($carIdCode === '') {
            return [];
        }

        $patterns = [
            self::jobPrefix($carIdCode).$carIdCode.'%',
        ];

        if (strlen($carIdCode) >= 2) {
            $patterns[] = self::jobPrefix($carIdCode).substr($carIdCode, 0, 2).'xx%';
        }

        if (strlen($carIdCode) >= 1) {
            $patterns[] = self::jobPrefix($carIdCode).substr($carIdCode, 0, 1).'xxx%';
        }

        return array_values(array_unique($patterns));
    }

    public static function matchesCar(string $segment, string $carIdCode): bool
    {
        $segment = strtoupper(trim($segment));
        $carIdCode = strtoupper(trim($carIdCode));

        if ($segment === '' || $carIdCode === '') {
            return false;
        }

        if ($segment === $carIdCode) {
            return true;
        }

        if (self::containsWildcard($segment)) {
            return self::wildcardMatchesCar($segment, $carIdCode);
        }

        return false;
    }

    public static function matchRank(string $segment, string $carIdCode): int
    {
        $segment = strtoupper(trim($segment));
        $carIdCode = strtoupper(trim($carIdCode));

        if ($segment === $carIdCode) {
            return 3;
        }

        if (self::containsWildcard($segment) && self::wildcardMatchesCar($segment, $carIdCode)) {
            if (str_starts_with($segment, substr($carIdCode, 0, 2).'x')
                || str_starts_with($segment, substr($carIdCode, 0, 2).'X')) {
                return 2;
            }

            return 1;
        }

        return 0;
    }

    private static function jobPrefix(string $carIdCode): string
    {
        return '____';
    }

    private static function containsWildcard(string $segment): bool
    {
        return strpbrk($segment, 'xX') !== false;
    }

    private static function wildcardMatchesCar(string $segment, string $carIdCode): bool
    {
        if (strlen($segment) !== strlen($carIdCode)) {
            return false;
        }

        for ($index = 0; $index < strlen($segment); $index++) {
            $segmentChar = $segment[$index];
            $carChar = $carIdCode[$index] ?? '';

            if ($segmentChar === 'x' || $segmentChar === 'X') {
                continue;
            }

            if ($segmentChar !== $carChar) {
                return false;
            }
        }

        return true;
    }
}
