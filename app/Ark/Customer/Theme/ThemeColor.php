<?php

declare(strict_types=1);

namespace App\Ark\Customer\Theme;

/**
 * sRGB color arithmetic for the customer theme: WCAG relative luminance and contrast, and mixing.
 * Every color here is a validated lowercase #rrggbb string.
 */
final class ThemeColor
{
    private const HEX = '/^#[0-9a-f]{6}$/';

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return preg_match(self::HEX, $value) === 1 ? $value : null;
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    public static function channels(string $hex): string
    {
        return implode(' ', self::rgb($hex));
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** $weight of $other mixed into $base (0..1). */
    public static function mix(string $base, string $other, float $weight): string
    {
        $a = self::rgb($base);
        $b = self::rgb($other);
        $mixed = array_map(fn (int $x, int $y): int => (int) round($x + ($y - $x) * $weight), $a, $b);

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    /**
     * $color moved toward $toward in small steps until it reaches $ratio against every background,
     * or $toward itself.
     *
     * @param  list<string>  $backgrounds
     */
    public static function untilContrast(string $color, string $toward, array $backgrounds, float $ratio): string
    {
        for ($step = 0; $step <= 20; $step++) {
            $candidate = self::mix($color, $toward, $step / 20);
            $passes = array_filter($backgrounds, fn (string $bg): bool => self::contrast($candidate, $bg) >= $ratio);
            if (count($passes) === count($backgrounds)) {
                return $candidate;
            }
        }

        return $toward;
    }

    /** The more legible of $light and $dark on $background. */
    public static function readableOn(string $background, string $light = '#ffffff', string $dark = '#111827'): string
    {
        return self::contrast($light, $background) >= self::contrast($dark, $background) ? $light : $dark;
    }
}
