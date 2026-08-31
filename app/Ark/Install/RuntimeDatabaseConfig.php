<?php

namespace App\Ark\Install;

/**
 * Effective database settings already known to the running application.
 *
 * Compose injects DB_* into the process environment; Laravel resolves them
 * into config('database.connections.*'). Passwords are available to PHP for
 * connection testing but must never be rendered into HTML, logs, or drafts.
 */
final class RuntimeDatabaseConfig
{
    public static function isManaged(): bool
    {
        if (! filter_var(config('install.managed_database', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $runtime = self::read();

        return $runtime['host'] !== ''
            && $runtime['database'] !== ''
            && $runtime['username'] !== '';
    }

    /**
     * @return array{
     *     connection: string,
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: string,
     *     password: string,
     * }
     */
    public static function read(): array
    {
        $connection = (string) config('database.default', 'mysql');
        $cfg = config('database.connections.'.$connection, []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $host = trim((string) ($cfg['host'] ?? ''));
        $database = trim((string) ($cfg['database'] ?? ''));
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $port = (int) ($cfg['port'] ?? 3306);
        if ($port < 1 || $port > 65535) {
            $port = 3306;
        }

        return [
            'connection' => $connection !== '' ? $connection : 'mysql',
            'host' => $host !== '' ? $host : '127.0.0.1',
            'port' => $port,
            'database' => $database !== '' ? $database : 'ark',
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Non-secret form defaults: draft (explicit prior step) wins, else runtime.
     *
     * @param  array<string, mixed>  $draft
     * @return array{
     *     db_host: string,
     *     db_port: int,
     *     db_database: string,
     *     db_username: string,
     *     runtime_password_configured: bool,
     * }
     */
    public static function formDefaults(array $draft): array
    {
        $runtime = self::read();

        return [
            'db_host' => (string) ($draft['db_host'] ?? $runtime['host']),
            'db_port' => (int) ($draft['db_port'] ?? $runtime['port']),
            'db_database' => (string) ($draft['db_database'] ?? $runtime['database']),
            'db_username' => (string) ($draft['db_username'] ?? $runtime['username']),
            'runtime_password_configured' => $runtime['password'] !== '',
        ];
    }

    /**
     * Resolve the password used for a connection test / install.
     *
     * Precedence:
     *   1. Non-empty submitted password (explicit operator input)
     *   2. Runtime password when submitted identity matches runtime identity
     *   3. Session password from a prior successful test of the same identity
     *   4. Empty string
     *
     * An empty password field therefore does NOT wipe a platform-provided
     * password when the operator leaves the runtime identity unchanged.
     *
     * @param  array{host: string, port: string|int, database: string, username: string}  $identity
     */
    public static function resolvePassword(string $submitted, array $identity): string
    {
        if ($submitted !== '') {
            return $submitted;
        }

        $runtime = self::read();
        if (self::identityMatches($identity, $runtime) && $runtime['password'] !== '') {
            return $runtime['password'];
        }

        $sessionPassword = (string) session('install.db_password', '');
        if ($sessionPassword === '') {
            return '';
        }

        $draft = InstallDraft::all();
        $prior = [
            'host' => (string) ($draft['db_host'] ?? ''),
            'port' => (int) ($draft['db_port'] ?? 0),
            'database' => (string) ($draft['db_database'] ?? ''),
            'username' => (string) ($draft['db_username'] ?? ''),
        ];

        if (self::identityMatches($identity, $prior)) {
            return $sessionPassword;
        }

        return '';
    }

    /**
     * @param  array{host?: string, port?: string|int, database?: string, username?: string}  $a
     * @param  array{host?: string, port?: string|int, database?: string, username?: string}  $b
     */
    public static function identityMatches(array $a, array $b): bool
    {
        return trim((string) ($a['host'] ?? '')) === trim((string) ($b['host'] ?? ''))
            && (int) ($a['port'] ?? 0) === (int) ($b['port'] ?? 0)
            && trim((string) ($a['database'] ?? '')) === trim((string) ($b['database'] ?? ''))
            && (string) ($a['username'] ?? '') === (string) ($b['username'] ?? '');
    }
}
