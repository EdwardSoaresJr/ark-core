<?php

namespace App\Ark\Install;

use Illuminate\Support\Str;

/**
 * Installation-scoped secret for Essential Delivery before Cloud pairing.
 * After pairing, PlatformConnection credential is preferred for signing.
 */
final class EssentialDeliverySecret
{
    private const RELATIVE_PATH = 'essential_delivery_secret';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE_PATH);
    }

    public static function getOrCreate(): string
    {
        $path = self::path();
        if (is_file($path)) {
            $existing = trim((string) file_get_contents($path));
            if (strlen($existing) >= 32) {
                return $existing;
            }
        }

        $secret = 'arkessential_'.Str::random(48);
        self::write($secret);

        return $secret;
    }

    public static function write(string $secret): void
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Essential delivery secret is too short.');
        }

        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $secret.PHP_EOL);
        @chmod($path, 0600);
    }
}
