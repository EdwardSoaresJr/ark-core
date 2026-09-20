<?php

namespace App\Ark\Install;

/**
 * Temporary install input for background finalize. Deleted when install finishes.
 * Passwords live here briefly so the HTTP request can return before migrate.
 */
final class PendingInstallPayload
{
    private const RELATIVE = 'pending_install.json';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function write(array $input): void
    {
        $dir = dirname(self::path());
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Unable to create install payload directory.');
        }

        $tmp = self::path().'.tmp';
        $json = json_encode($input, JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write install payload.');
        }
        @chmod($tmp, 0600);
        if (! @rename($tmp, self::path())) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to finalize install payload.');
        }
        @chmod(self::path(), 0600);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        if (! is_file(self::path())) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents(self::path()), true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function clear(): void
    {
        if (is_file(self::path())) {
            @unlink(self::path());
        }
    }
}
