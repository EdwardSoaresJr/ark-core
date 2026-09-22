<?php

namespace App\Ark\Platform;

final class BoxSourceCommit
{
    /**
     * @return non-empty-string|null
     */
    public static function read(): ?string
    {
        $configured = self::usable(config('app.commit'));
        if ($configured !== null) {
            return $configured;
        }

        foreach (self::paths() as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $fromFile = self::usable(file_get_contents($path));
            if ($fromFile !== null) {
                return $fromFile;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return [
            base_path('.ark-source-commit'),
            '/app/.ark-source-commit',
        ];
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
