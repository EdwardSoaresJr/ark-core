<?php

namespace App\Ark\Install;

/**
 * File-backed installation authority. Never uses the application database.
 *
 * States: not_installed | in_progress | installed
 */
final class InstallationState
{
    public const NOT_INSTALLED = 'not_installed';

    public const IN_PROGRESS = 'in_progress';

    public const INSTALLED = 'installed';

    private const RELATIVE_PATH = 'state.json';

    public static function path(): string
    {
        return InstallStorage::path(self::RELATIVE_PATH);
    }

    public static function status(): string
    {
        $data = self::read();

        return $data['status'] ?? self::NOT_INSTALLED;
    }

    public static function isInstalled(): bool
    {
        return self::status() === self::INSTALLED;
    }

    public static function isInProgress(): bool
    {
        return self::status() === self::IN_PROGRESS;
    }

    public static function isNotInstalled(): bool
    {
        return ! self::isInstalled();
    }

    /**
     * @return array{status: string, updated_at: ?string, checkpoint: ?string, meta: array<string, mixed>}
     */
    public static function read(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return [
                'status' => self::NOT_INSTALLED,
                'updated_at' => null,
                'checkpoint' => null,
                'meta' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [
                'status' => self::NOT_INSTALLED,
                'updated_at' => null,
                'checkpoint' => null,
                'meta' => [],
            ];
        }

        return [
            'status' => (string) ($decoded['status'] ?? self::NOT_INSTALLED),
            'updated_at' => isset($decoded['updated_at']) ? (string) $decoded['updated_at'] : null,
            'checkpoint' => isset($decoded['checkpoint']) ? (string) $decoded['checkpoint'] : null,
            'meta' => is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [],
        ];
    }

    public static function markInProgress(?string $checkpoint = null): void
    {
        self::write(self::IN_PROGRESS, $checkpoint);
    }

    public static function markInstalled(): void
    {
        self::write(self::INSTALLED, 'complete', []);
    }

    public static function markFailed(?string $checkpoint = null, ?string $message = null): void
    {
        $meta = self::read()['meta'] ?? [];
        if ($message !== null && $message !== '') {
            $meta['error'] = $message;
        }

        self::write(self::IN_PROGRESS, $checkpoint ?? 'failed', $meta);
    }

    /**
     * Terminal failure checkpoints — install stopped; stranger can retry from Review.
     */
    public static function hasFailedCheckpoint(): bool
    {
        if (self::status() !== self::IN_PROGRESS) {
            return false;
        }

        $checkpoint = self::read()['checkpoint'] ?? null;

        return in_array($checkpoint, [
            'failed',
            'exception',
            'database_test',
            'database_safety',
            'migrate_failed',
            'mutex',
            'locked',
        ], true);
    }

    public static function isActivelyInstalling(): bool
    {
        return self::isInProgress() && ! self::hasFailedCheckpoint();
    }

    public static function failureMessage(): ?string
    {
        $error = self::read()['meta']['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    /**
     * Human label for the current checkpoint (progress UI).
     */
    public static function checkpointLabel(?string $checkpoint = null): string
    {
        $checkpoint ??= self::read()['checkpoint'] ?? null;

        return match ($checkpoint) {
            'queued' => 'Starting installation…',
            'start' => 'Checking database…',
            'bootstrap' => 'Writing application configuration…',
            'migrate' => 'Creating database tables…',
            'authorize' => 'Setting up roles…',
            'status_catalog' => 'Loading operational catalogs…',
            'admin' => 'Creating administrator…',
            'shop' => 'Applying shop settings…',
            'workstation' => 'Creating default workstation…',
            'complete' => 'Installation complete.',
            'database_test', 'database_safety' => 'Database check failed.',
            'migrate_failed' => 'Database migration failed.',
            'exception', 'failed' => 'Installation failed.',
            'mutex' => 'Another installation is already running.',
            'locked' => 'ARK is already installed.',
            default => $checkpoint ? 'Working… ('.$checkpoint.')' : 'Working…',
        };
    }

    /**
     * Safe recovery for interrupted IN_PROGRESS only — never clears INSTALLED.
     */
    public static function recoverInterruptedProgress(): bool
    {
        if (self::status() !== self::IN_PROGRESS) {
            return false;
        }

        self::write(self::NOT_INSTALLED, null, []);

        return true;
    }

    /**
     * Testing helper — never call from application code.
     */
    public static function resetForTests(): void
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('resetForTests is only available in the testing environment.');
        }

        self::write(self::NOT_INSTALLED, null, []);
        InstallDraft::clear();
        PendingInstallPayload::clear();
        @unlink(InstallationMutex::path());
    }

    /**
     * @param  array<string, mixed>|null  $meta  null keeps existing meta
     */
    private static function write(string $status, ?string $checkpoint, ?array $meta = null): void
    {
        $payload = json_encode([
            'status' => $status,
            'updated_at' => gmdate('c'),
            'checkpoint' => $checkpoint,
            'meta' => $meta ?? (self::read()['meta'] ?? []),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Unable to create install state directory: '.$dir);
        }

        $tmp = $path.'.tmp';
        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write install state: '.$tmp);
        }

        if (! @rename($tmp, $path)) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Unable to recreate install state directory: '.$dir);
            }

            if (! rename($tmp, $path)) {
                @unlink($tmp);
                throw new \RuntimeException('Unable to finalize install state: '.$path);
            }
        }
    }
}
