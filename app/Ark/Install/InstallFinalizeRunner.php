<?php

namespace App\Ark\Install;

use Illuminate\Support\Facades\Artisan;

/**
 * Starts ark:install-finalize outside the HTTP request so proxies cannot abort migrate.
 */
final class InstallFinalizeRunner
{
    /** @var (callable(): void)|null */
    public static $fakeStart = null;

    public static function start(): void
    {
        if (self::$fakeStart !== null) {
            (self::$fakeStart)();

            return;
        }

        if (app()->environment('testing')) {
            Artisan::call('ark:install-finalize');

            return;
        }

        $php = self::phpCliBinary();
        $artisan = base_path('artisan');
        $log = storage_path('logs/install-finalize.log');
        $logDir = dirname($log);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $cmd = sprintf(
            'nohup %s %s ark:install-finalize >> %s 2>&1 < /dev/null &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($log)
        );

        exec($cmd);
    }

    /**
     * PHP_BINARY under php-fpm is the FPM binary and cannot run artisan.
     */
    public static function phpCliBinary(): string
    {
        $candidates = [];

        if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
            $candidates[] = rtrim(PHP_BINDIR, '/').'/php';
        }

        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        if (PHP_BINARY !== '' && ! self::looksLikeFpmBinary(PHP_BINARY)) {
            $candidates[] = PHP_BINARY;
        }

        $candidates[] = 'php';

        foreach ($candidates as $candidate) {
            if ($candidate === 'php') {
                return $candidate;
            }

            if (is_string($candidate) && $candidate !== '' && is_executable($candidate) && ! self::looksLikeFpmBinary($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    private static function looksLikeFpmBinary(string $path): bool
    {
        return str_contains(strtolower(basename($path)), 'fpm');
    }
}
