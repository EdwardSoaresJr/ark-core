<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Facades\Log;
use Throwable;

final class PdfRuntimeConfigurator
{
    public static function apply(): void
    {
        self::ensureExecutable('node_binary', static fn (): ?string => PdfRuntimePaths::resolveNodeBinary());
        self::ensureExecutable(
            'npm_binary',
            static fn (): ?string => PdfRuntimePaths::resolveNpmBinary(config('services.pdf.node_binary')),
        );
        self::ensureExecutable('chrome_path', static fn (): ?string => PdfRuntimePaths::resolveChromePath());

        $node = config('services.pdf.node_binary');

        if (! filled(config('services.pdf.include_path')) && filled($node)) {
            config(['services.pdf.include_path' => dirname((string) $node)]);
        }

        if (! filter_var(config('services.pdf.no_sandbox'), FILTER_VALIDATE_BOOLEAN) && self::runningInLinuxContainer()) {
            config(['services.pdf.no_sandbox' => true]);
        }
    }

    private static function runningInLinuxContainer(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && (is_file('/.dockerenv') || is_file('/run/.containerenv'));
    }

    private static function ensureExecutable(string $configKey, callable $resolver): void
    {
        $configPath = "services.pdf.{$configKey}";
        $current = config($configPath);

        if (self::isUsableExecutable($current)) {
            return;
        }

        if (filled($current)) {
            Log::warning('PDF runtime path is missing or not executable; re-resolving.', [
                'key' => $configKey,
                'path' => $current,
            ]);
        }

        try {
            $resolved = $resolver();
        } catch (Throwable $exception) {
            Log::warning('PDF runtime discovery failed.', [
                'key' => $configKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (self::isUsableExecutable($resolved)) {
            config([$configPath => $resolved]);
        }
    }

    private static function isUsableExecutable(mixed $path): bool
    {
        return is_string($path) && $path !== '' && is_executable($path);
    }
}
