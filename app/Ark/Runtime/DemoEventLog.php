<?php

namespace App\Ark\Runtime;

final class DemoEventLog
{
    public static function sourcePath(mixed $value): string
    {
        if (! is_string($value)) {
            return '/';
        }

        $path = rawurldecode($value);
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n\\\\]/', $path) === 1) {
            return '/';
        }

        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        if ($path === '') {
            return '/';
        }

        if (strlen($path) > 200) {
            $path = substr($path, 0, 200);
        }

        return $path;
    }

    public static function record(string $event, string $sourcePath): void
    {
        $directory = dirname(self::path());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $line = json_encode([
            'event' => $event,
            'source_path' => self::sourcePath($sourcePath),
            'at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        file_put_contents(self::path(), $line."\n", FILE_APPEND | LOCK_EX);
    }

    public static function path(): string
    {
        $configured = config('ark.demo_events');

        return is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('app/private/demo-events.jsonl');
    }
}
