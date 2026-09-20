<?php

namespace App\Ark\Install;

/**
 * Non-secret wizard draft persisted to disk between steps.
 * Passwords and API secrets never belong here — session only.
 */
final class InstallDraft
{
    private const RELATIVE = 'draft.json';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (! is_file(self::path())) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents(self::path()), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function merge(array $patch): void
    {
        // Refuse secrets if accidentally passed.
        unset($patch['password'], $patch['password_confirmation'], $patch['db_password'], $patch['openai_api_key']);

        $data = array_merge(self::all(), $patch);
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = self::path().'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        rename($tmp, self::path());
    }

    public static function clear(): void
    {
        if (is_file(self::path())) {
            @unlink(self::path());
        }
    }
}
