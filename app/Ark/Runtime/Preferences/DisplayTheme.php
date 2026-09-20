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

    /**
     * Fresh users / missing preference default to light.
     *
     * Dark mode is incomplete; "system" remains an explicit saved choice for
     * users who pick it, but it must not be the first-run implicit default.
     */
    public static function default(): self
    {
        return self::Light;
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
