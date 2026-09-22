<?php

namespace App\Ark\Platform;

final class BoxSourceCommit
{
    /**
     * @return non-empty-string|null
     */
    public static function read(): ?string
    {
        foreach (self::paths() as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $fromFile = self::usable(file_get_contents($path));
            if ($fromFile !== null) {
                return $fromFile;
            }
        }

        return self::usable(config('app.commit'));
    }

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        $configured = config('app.source_commit_file');
        $paths = [];
        if (is_string($configured) && trim($configured) !== '') {
            $paths[] = trim($configured);
        }

        foreach ([base_path('.ark-source-commit'), '/app/.ark-source-commit'] as $path) {
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @return non-empty-string|null
     */
    private static function usable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'unknown') === 0) {
            return null;
        }

        return substr($value, 0, 40);
    }
}
