<?php

namespace App\Ark\Install;

/**
 * Atomic filesystem mutex for the final Install ARK step.
 */
final class InstallationMutex
{
    private const RELATIVE = 'install.lock';

    /** @var resource|null */
    private static $handle = null;

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE);
    }

    public static function acquire(): bool
    {
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen(self::path(), 'c+');
        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        self::$handle = $handle;
        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid()."\n".gmdate('c')."\n");
        fflush($handle);

        return true;
    }

    public static function release(): void
    {
        if (self::$handle === null) {
            return;
        }

        flock(self::$handle, LOCK_UN);
        fclose(self::$handle);
        self::$handle = null;
    }
}
