<?php

namespace App\Ark\Install;

use Illuminate\Support\Str;

/**
 * Durable non-secret installation identity.
 * Survives container recreation when storage/app/install is persisted.
 * Does not authenticate the Box to ARK Cloud.
 */
final class InstallationIdentity
{
    private const RELATIVE_PATH = 'installation_uuid';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE_PATH);
    }

    public static function uuid(): string
    {
        $path = self::path();
        if (is_file($path)) {
            $existing = trim((string) file_get_contents($path));
            if (Str::isUuid($existing)) {
                return $existing;
            }
        }

        $uuid = (string) Str::uuid();
        self::write($uuid);

        return $uuid;
    }

    public static function write(string $uuid): void
    {
        if (! Str::isUuid($uuid)) {
            throw new \InvalidArgumentException('installation uuid must be a UUID');
        }

        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $uuid.PHP_EOL);
        @chmod($path, 0644);
    }
}
