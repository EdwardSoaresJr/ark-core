<?php

namespace App\Ark\Platform;

use Illuminate\Foundation\Application;

final class BoxRuntimeObservation
{
    /**
     * @return array{core_version: string, release: ?string, commit: ?string, laravel_version: string, php_version: string}
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
        ];
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
