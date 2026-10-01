<?php

namespace App\Ark\Runtime\Preferences;

enum AccentTheme: string
{
    case Ark2 = 'ark2';
    case Orange = 'orange';
    case Blue = 'blue';
    case Emerald = 'emerald';
    case Violet = 'violet';
    case Rose = 'rose';
    case Amber = 'amber';
    case Sky = 'sky';
    case Teal = 'teal';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Ark2 => 'ARK 2',
            self::Orange => 'Orange',
            self::Blue => 'Blue',
            self::Emerald => 'Emerald',
            self::Violet => 'Violet',
            self::Rose => 'Pink',
            self::Amber => 'Amber',
            self::Sky => 'Sky',
            self::Teal => 'Teal',
            self::Custom => 'Custom',
        };
    }

    public static function default(): self
    {
        return self::Ark2;
    }

    public static function tryFromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $theme) => $theme->value,
            self::cases(),
        );
    }
}
