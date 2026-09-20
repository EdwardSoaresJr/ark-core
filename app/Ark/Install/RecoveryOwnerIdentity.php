<?php

namespace App\Ark\Install;

/**
 * Bootstrap recovery identity captured during setup.
 */
final class RecoveryOwnerIdentity
{
    private const RELATIVE_PATH = 'recovery_owner_email';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE_PATH);
    }

    public static function email(): ?string
    {
        $path = self::path();
        if (is_file($path)) {
            $email = strtolower(trim((string) file_get_contents($path)));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        if (! \App\Ark\Install\InstallationState::isInstalled()) {
            return null;
        }

        $master = \App\Models\User::query()
            ->where('is_master_admin', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('email');

        return is_string($master) && filter_var($master, FILTER_VALIDATE_EMAIL)
            ? strtolower($master)
            : null;
    }

    public static function write(string $email): void
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Recovery owner email is invalid.');
        }

        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $email.PHP_EOL);
        @chmod($path, 0644);
    }
}
