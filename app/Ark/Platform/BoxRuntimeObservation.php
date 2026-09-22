<?php

namespace App\Ark\Platform;

use Illuminate\Foundation\Application;

final class BoxRuntimeObservation
{
    /**
     * @return array{
     *     core_version: string,
     *     release: ?string,
     *     commit: ?string,
     *     php_version: string,
     *     laravel_version: string,
     *     image_digest: ?string
     * }
     */
    public static function payload(): array
    {
        $commit = self::sourceCommit();

        return [
            'core_version' => self::coreVersion($commit),
            'release' => self::optionalString(config('app.release'), 64),
            'commit' => $commit,
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'image_digest' => self::imageDigest(),
        ];
    }

    private static function coreVersion(?string $commit): string
    {
        $configured = self::optionalString(config('app.version'), 64);
        if ($configured !== null) {
            return $configured;
        }

        if ($commit !== null) {
            return substr($commit, 0, 12);
        }

        return 'dev';
    }

    private static function sourceCommit(): ?string
    {
        $fromFile = self::readSourceCommitFile();
        if ($fromFile !== null) {
            return $fromFile;
        }

        return self::optionalString(config('app.commit'), 40);
    }

    private static function readSourceCommitFile(): ?string
    {
        $path = config('app.source_commit_file');
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $commit = self::optionalString($contents, 40);
        if ($commit === null || strtolower($commit) === 'unknown') {
            return null;
        }

        return $commit;
    }

    private static function imageDigest(): ?string
    {
        $digest = self::optionalString(config('app.image_digest'), 71);
        if ($digest === null) {
            return null;
        }

        $digest = strtolower($digest);
        if (preg_match('/^sha256:[a-f0-9]{64}$/', $digest) !== 1) {
            return null;
        }

        return $digest;
    }

    private static function optionalString(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, $max);
    }
}
