<?php

namespace App\Ark\Install;

/**
 * Deployment install entry. The request cannot change it.
 */
final class InstallMode
{
    public const SELF_HOSTED = 'self_hosted';

    public const MANAGED = 'managed';

    public static function current(): string
    {
        return config('install.mode') === self::MANAGED
            ? self::MANAGED
            : self::SELF_HOSTED;
    }

    public static function isManaged(): bool
    {
        return self::current() === self::MANAGED;
    }
}
