<?php

namespace App\Ark\Operations\Communications;

final class CommunicationsAccentColor
{
    public const NEUTRAL = 'neutral';

    public const SLATE = 'slate';

    public const BLUE = 'blue';

    public const AMBER = 'amber';

    public const ORANGE = 'orange';

    public const GREEN = 'green';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::NEUTRAL,
            self::SLATE,
            self::BLUE,
            self::AMBER,
            self::ORANGE,
            self::GREEN,
        ];
    }

    /**
     * @return list<array{key: string, label: string, swatch: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $key): array => [
                'key' => $key,
                'label' => self::label($key),
                'swatch' => self::swatch($key),
            ],
            self::keys(),
        );
    }

    public static function normalize(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return in_array($color, self::keys(), true) ? $color : self::NEUTRAL;
    }

    public static function label(?string $color): string
    {
        return match (self::normalize($color)) {
            self::SLATE => 'Slate',
            self::BLUE => 'Blue',
            self::AMBER => 'Amber',
            self::ORANGE => 'Orange',
            self::GREEN => 'Green',
            default => 'Neutral',
        };
    }

    public static function swatch(?string $color): string
    {
        return match (self::normalize($color)) {
            self::SLATE => '#334155',
            self::BLUE => '#0284c7',
            self::AMBER => '#d97706',
            self::ORANGE => '#ea580c',
            self::GREEN => '#059669',
            default => '#94a3b8',
        };
    }

    public static function chipStyle(?string $color): ?string
    {
        return match (self::normalize($color)) {
            self::SLATE => 'background:#f1f5f9;border-color:#94a3b8;color:#1e293b',
            self::BLUE => 'background:#f0f9ff;border-color:#7dd3fc;color:#0c4a6e',
            self::AMBER => 'background:#fffbeb;border-color:#fcd34d;color:#78350f',
            self::ORANGE => 'background:#fff7ed;border-color:#fdba74;color:#7c2d12',
            self::GREEN => 'background:#ecfdf5;border-color:#6ee7b7;color:#064e3b',
            default => null,
        };
    }
}
