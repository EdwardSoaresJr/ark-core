<?php

namespace App\Ark\Platform;

use Illuminate\Foundation\Application;

final class BoxRuntimeObservation
{
    /**
     * @return array{core_version: string, release: ?string, commit: ?string, laravel_version: string, php_version: string, image_digest: ?string}
     */
    public static function payload(): array
    {
        $commit = BoxSourceCommit::read();
        $release = config('app.release');
        $version = config('app.version');

        return [
            'core_version' => self::coreVersion(is_string($version) ? $version : null, $commit),
            'release' => self::optionalString($release),
            'commit' => $commit,
            'laravel_version' => Application::VERSION,
            'php_version' => PHP_VERSION,
            'image_digest' => self::imageDigest(),
        ];
    }

    /**
     * @return non-empty-string|null
     */
    public static function imageDigest(): ?string
    {
        $digest = self::optionalString(config('app.image_digest'));
        if ($digest === null) {
            return null;
        }

        if (preg_match('/sha256:[a-f0-9]{64}/i', $digest, $match) !== 1) {
            return null;
        }

        return strtolower($match[0]);
    }

    private static function coreVersion(?string $version, ?string $commit): string
    {
        $version = self::optionalString($version);
        if ($version !== null) {
            return $version;
        }

        if ($commit !== null) {
            return substr($commit, 0, 12);
        }

        return 'dev';
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'unknown') === 0) {
            return null;
        }

        return $value;
    }
}
