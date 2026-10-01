<?php

namespace App\Ark\Operations\Scoreboard;

final class ShopOperatingScoreboardMath
{
    public static function perOpenDay(float|int|null $numerator, int $openDays, int $decimals = 2): ?float
    {
        if ($numerator === null || $openDays < 1) {
            return null;
        }

        return round(((float) $numerator) / $openDays, $decimals);
    }

    /**
     * @param  list<float|int>  $values
     */
    public static function median(array $values, int $decimals = 2): ?float
    {
        $sorted = self::sorted($values);

        if ($sorted === []) {
            return null;
        }

        $count = count($sorted);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return round($sorted[$middle], $decimals);
        }

        return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, $decimals);
    }

    /**
     * @param  list<float|int>  $values
     */
    public static function average(array $values, int $decimals = 2): ?float
    {
        $sorted = self::sorted($values);

        if ($sorted === []) {
            return null;
        }

        return round(array_sum($sorted) / count($sorted), $decimals);
    }

    /**
     * @return 'good'|'warn'|null
     */
    public static function toneForMinimum(?float $actual, ?float $target): ?string
    {
        if ($actual === null || $target === null) {
            return null;
        }

        return $actual >= $target ? 'good' : 'warn';
    }

    /**
     * @return 'good'|'warn'|null
     */
    public static function toneForMaximum(?float $actual, ?float $target): ?string
    {
        if ($actual === null || $target === null) {
            return null;
        }

        return $actual <= $target ? 'good' : 'warn';
    }

    /**
     * @param  list<float|int>  $values
     * @return list<float>
     */
    private static function sorted(array $values): array
    {
        $sorted = array_values(array_map(static fn (float|int $value): float => (float) $value, $values));
        sort($sorted);

        return $sorted;
    }
}
