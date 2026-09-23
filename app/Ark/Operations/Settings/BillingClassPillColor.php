<?php

namespace App\Ark\Operations\Settings;

final class BillingClassPillColor
{
    public const SLATE = 'slate';

    public const BLUE = 'blue';

    public const GREEN = 'green';

    public const AMBER = 'amber';

    public const ORANGE = 'orange';

    public const ROSE = 'rose';

    public const VIOLET = 'violet';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::SLATE,
            self::BLUE,
            self::GREEN,
            self::AMBER,
            self::ORANGE,
            self::ROSE,
            self::VIOLET,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $key): array => [
                'key' => $key,
                'label' => self::label($key),
            ],
            self::keys(),
        );
    }

    public static function label(string $color): string
    {
        return match (self::normalize($color)) {
            self::BLUE => 'Blue',
            self::GREEN => 'Green',
            self::AMBER => 'Amber',
            self::ORANGE => 'Orange',
            self::ROSE => 'Rose',
            self::VIOLET => 'Violet',
            default => 'Slate',
        };
    }

    public static function normalize(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return in_array($color, self::keys(), true) ? $color : self::SLATE;
    }

    public static function resolve(?string $stored, string $name): string
    {
        $color = strtolower(trim((string) $stored));

        if (in_array($color, self::keys(), true)) {
            return $color;
        }

        return self::defaultFor($name);
    }

    public static function defaultFor(string $name): string
    {
        return match (strtolower(trim($name))) {
            'fleet', 'repairpal' => self::BLUE,
            'warranty', 'military' => self::GREEN,
            'wholesale' => self::AMBER,
            'comeback' => self::ORANGE,
            'internal' => self::VIOLET,
            default => self::SLATE,
        };
    }

    public static function classFor(?string $stored, string $name): string
    {
        return 'ops-billing-class-pill ops-billing-class-pill--'.self::resolve($stored, $name);
    }
}
