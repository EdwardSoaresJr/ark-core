<?php

namespace App\Ark\Runtime\Preferences;

enum DisplayTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::System => 'Match system',
        };
    }

    public static function default(): self
    {
        return self::System;
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

    public function resolvesToDark(bool $prefersDark): bool
    {
        return match ($this) {
            self::Dark => true,
            self::Light => false,
            self::System => $prefersDark,
        };
    }
}
