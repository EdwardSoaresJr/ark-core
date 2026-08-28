<?php

namespace App\Ark\Runtime\Preferences;

final class AccentColor
{
    public const string DefaultHex = '#ff12b0';

    public static function normalize(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $hex = ltrim(trim($value), '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return '#'.strtolower($hex);
    }

    public static function resolve(?string $value): string
    {
        return self::normalize($value) ?? self::DefaultHex;
    }

    public static function htmlStyleAttribute(?string $value): string
    {
        $hex = self::resolve($value);

        return '--ops-accent-custom: '.$hex.';';
    }
}
