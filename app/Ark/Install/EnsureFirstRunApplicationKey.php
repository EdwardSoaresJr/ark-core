<?php

namespace App\Ark\Install;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Establish a stable APP_KEY early enough for /setup on writable hosts.
 *
 * Docker does this in entrypoint.sh before PHP. Herd/local PHP must do the
 * equivalent before EncryptCookies resolves the encrypter.
 *
 * Only runs while ARK is not installed. Never rotates keys after install.
 */
final class EnsureFirstRunApplicationKey
{
    private const KEY_FILE = 'app_key';

    private const LOCK_FILE = 'app_key.lock';

    public function __construct(
        private readonly InstallerEnvironmentWriter $envWriter,
    ) {}

    public function ensure(): void
    {
        $current = $this->runtimeKey();
        if ($current !== '') {
            return;
        }

        if (InstallationState::isInstalled()) {
            throw new RuntimeException(
                'APP_KEY is missing on an installed ARK instance. Restore APP_KEY from your environment backup; automatic key generation is disabled after installation.'
            );
        }

        // Canonical Docker persists APP_KEY to install storage in entrypoint.sh.
        // Immutable hosts (no writable /.env) apply that durable key at runtime only.
        // Writable hosts also sync it into .env so Herd/local stays coherent.
        $fromKeyFile = $this->readKeyFile();
        if ($fromKeyFile !== '') {
            if ($this->envWriter->mode() === 'writable') {
                $this->persistAndApply($fromKeyFile);
            } else {
                $this->applyRuntimeKey($fromKeyFile);
            }

            return;
        }

        if ($this->envWriter->mode() !== 'writable') {
            throw new RuntimeException(
                'APP_KEY is missing and the environment is not writable. Supply APP_KEY via the hosting platform environment, then reload.'
            );
        }

        $this->withExclusiveLock(function (): void {
            $current = $this->runtimeKey();
            if ($current !== '') {
                return;
            }

            $fromEnvFile = $this->readKeyFromEnvFile();
            if ($fromEnvFile !== '') {
                $this->applyRuntimeKey($fromEnvFile);

                return;
            }

            $fromKeyFile = $this->readKeyFile();
            if ($fromKeyFile !== '') {
                $this->persistAndApply($fromKeyFile);

                return;
            }

            $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher', 'AES-256-CBC')));
            $this->persistAndApply($key);
        });
    }

    private function persistAndApply(string $key): void
    {
        $this->envWriter->write(['APP_KEY' => $key]);
        $this->writeKeyFile($key);
        $this->applyRuntimeKey($key);
    }

    private function applyRuntimeKey(string $key): void
    {
        config(['app.key' => $key]);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
        putenv('APP_KEY='.$key);

        if (app()->bound('encrypter')) {
            app()->forgetInstance('encrypter');
        }
        if (app()->bound(\Illuminate\Contracts\Encryption\Encrypter::class)) {
            app()->forgetInstance(\Illuminate\Contracts\Encryption\Encrypter::class);
        }
    }

    private function runtimeKey(): string
    {
        $key = (string) config('app.key', '');
        if ($key !== '') {
            return $key;
        }

        $env = (string) (($_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY')) ?: '');

        return is_string($env) ? $env : '';
    }

    private function readKeyFromEnvFile(): string
    {
        $path = $this->envWriter->envPath();
        if (! is_file($path)) {
            return '';
        }

        $contents = (string) file_get_contents($path);
        // Use horizontal whitespace only after "=" — "\s*" would consume the newline
        // and capture the following .env assignment (e.g. APP_DEBUG / CUSTOM_KEEP).
        if (preg_match('/^\s*APP_KEY\s*=\h*([^\r\n]*)/m', $contents, $matches) !== 1) {
            return '';
        }

        $value = trim($matches[1]);
        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    private function keyFilePath(): string
    {
        return InstallStorage::path(self::KEY_FILE);
    }

    private function readKeyFile(): string
    {
        $path = $this->keyFilePath();
        if (! is_file($path)) {
            return '';
        }

        return trim((string) file_get_contents($path));
    }

    private function writeKeyFile(string $key): void
    {
        $path = $this->keyFilePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $path.'.tmp';
        file_put_contents($tmp, $key, LOCK_EX);
        rename($tmp, $path);
        @chmod($path, 0600);
    }

    /**
     * @param  callable(): void  $callback
     */
    private function withExclusiveLock(callable $callback): void
    {
        $path = InstallStorage::path(self::LOCK_FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to acquire application key lock.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire application key lock.');
            }
            $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
