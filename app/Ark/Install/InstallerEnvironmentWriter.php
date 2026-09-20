<?php

namespace App\Ark\Install;

use InvalidArgumentException;
use RuntimeException;

/**
 * Allowlisted .env writer for first-run bootstrap only.
 * Never accepts arbitrary keys from user input.
 */
final class InstallerEnvironmentWriter
{
    /** @var list<string> */
    public const ALLOWLIST = [
        'APP_KEY',
        'APP_URL',
        'APP_ENV',
        'APP_DEBUG',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'SESSION_DRIVER',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'SHOP_BASE_URL',
    ];

    /** @var list<string> */
    private const SECRET_KEYS = [
        'APP_KEY',
        'DB_PASSWORD',
    ];

    public function envPath(): string
    {
        return base_path('.env');
    }

    public function isWritable(): bool
    {
        $path = $this->envPath();
        if (is_file($path)) {
            return is_writable($path);
        }

        return is_writable(base_path());
    }

    public function mode(): string
    {
        return $this->isWritable() ? 'writable' : 'immutable';
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function write(array $values): void
    {
        foreach (array_keys($values) as $key) {
            if (! in_array($key, self::ALLOWLIST, true)) {
                throw new InvalidArgumentException("Environment key [{$key}] is not allowlisted for the installer.");
            }
        }

        if (! $this->isWritable()) {
            throw new RuntimeException('The application environment file is not writable. Supply bootstrap values via the hosting platform environment.');
        }

        $path = $this->envPath();
        $existing = is_file($path) ? (string) file_get_contents($path) : "";
        if ($existing === '' && is_file(base_path('.env.example'))) {
            $existing = (string) file_get_contents(base_path('.env.example'));
        }

        $lines = preg_split("/\r\n|\n|\r/", $existing) ?: [];
        $keysWritten = [];

        foreach ($values as $key => $value) {
            $rendered = $this->renderAssignment($key, $value);
            $replaced = false;
            $pattern = '/^\s*#?\s*'.preg_quote($key, '/').'\s*=/';
            // Replace every occurrence so later .env.example duplicates cannot win.
            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line) === 1) {
                    if (! $replaced) {
                        $lines[$i] = $rendered;
                        $replaced = true;
                    } else {
                        $lines[$i] = null;
                    }
                }
            }
            $lines = array_values(array_filter($lines, static fn ($line) => $line !== null));
            if (! $replaced) {
                $lines[] = $rendered;
            }
            $keysWritten[] = $key;
        }

        $content = implode("\n", $lines);
        if (! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        // Keep temp/backup under install storage — /app is often not writable for new siblings.
        $backupDir = storage_path('app/'.(app()->environment('testing') ? 'install/testing' : 'install'));
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $backup = $backupDir.'/dotenv.installer-bak';
        if (is_file($path) && is_writable($backupDir)) {
            @copy($path, $backup);
        }

        $tmp = $backupDir.'/dotenv.write-tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write environment file.');
        }
        if (! @rename($tmp, $path)) {
            // Cross-filesystem rename can fail; fall back to copy into the existing .env inode.
            if (! @copy($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException('Failed to atomically replace environment file.');
            }
            @unlink($tmp);
        }

        @chmod($path, 0600);

        foreach ($keysWritten as $key) {
            if (! in_array($key, self::SECRET_KEYS, true)) {
                // Non-secret confirmation only — never log secret values.
                logger()->info('installer.env.updated', ['key' => $key]);
            } else {
                logger()->info('installer.env.updated', ['key' => $key, 'secret' => true]);
            }
        }
    }

    private function renderAssignment(string $key, ?string $value): string
    {
        $value ??= '';

        if ($value === '' || preg_match('/\s|#|"|\'|\\\\|\$/', $value) === 1) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return $key.'="'.$escaped.'"';
        }

        return $key.'='.$value;
    }
}
