<?php

namespace App\Ark\Platform;

final class BoxRuntimeObservation
{
    /**
     * @return array{core_version: string, release: ?string, commit: ?string, php_version: string}
     */
    public static function payload(): array
    {
        $release = config('app.release');
        $commit = config('app.commit');

        return [
            'core_version' => (string) (config('app.version') ?: 'dev'),
            'release' => filled($release) ? (string) $release : null,
            'commit' => filled($commit) ? substr((string) $commit, 0, 40) : null,
            'php_version' => PHP_VERSION,
        ];
    }
}
