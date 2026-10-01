<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Fail-closed gate for automated destructive database testing.
 *
 * RefreshDatabase / LazilyRefreshDatabase are designed to destroy their target.
 * This assertion ensures the target is unmistakably disposable test infrastructure
 * before any migrate/refresh/truncate can run.
 */
final class AssertDisposableTestDatabase
{
    /**
     * Database names / paths that are always forbidden, even if APP_ENV=testing.
     *
     * @var list<string>
     */
    public const FORBIDDEN_EXACT = [
        'ark',           // public Core Herd developer DB (.env default)
        'arksmsv2',      // foundry local developer DB
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
    ];

    /**
     * Prefixes that identify certification / rehearsal / production-like evidence DBs.
     *
     * @var list<string>
     */
    public const FORBIDDEN_PREFIXES = [
        'lnp_restore_',
        'lnp_public_migrate_',
        'lnp_prod_',
        'production_',
        'prod_',
    ];

    /**
     * @throws RuntimeException
     */
    public static function abortUnlessSafe(
        string $appEnv,
        string $connection,
        ?string $database,
        ?string $driver = null,
    ): void {
        if ($appEnv !== 'testing') {
            throw new RuntimeException(
                self::message("APP_ENV must be 'testing' for automated destructive tests (got '{$appEnv}').")
            );
        }

        $database = is_string($database) ? trim($database) : '';
        if ($database === '') {
            throw new RuntimeException(
                self::message('Database name is empty - refusing destructive tests.')
            );
        }

        $normalized = strtolower($database);
        $driver = strtolower((string) ($driver ?: $connection));

        foreach (self::FORBIDDEN_EXACT as $exact) {
            if ($normalized === strtolower($exact)) {
                throw new RuntimeException(
                    self::message("Database '{$database}' is a forbidden non-test identity.")
                );
            }
        }

        foreach (self::FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($normalized, strtolower($prefix))) {
                throw new RuntimeException(
                    self::message("Database '{$database}' matches forbidden certification/rehearsal prefix '{$prefix}'.")
                );
            }
        }

        if (self::isPermitted($normalized, $driver)) {
            return;
        }

        throw new RuntimeException(
            self::message(
                "Database '{$database}' (connection={$connection}, driver={$driver}) is not an explicitly disposable test database. ".
                'Permitted: :memory:, testing, testing_*, ark_test_*, *_test, *_testing, */testing.sqlite.'
            )
        );
    }

    public static function isPermitted(string $normalizedDatabase, string $driver = 'sqlite'): bool
    {
        if ($normalizedDatabase === ':memory:') {
            return true;
        }

        if ($normalizedDatabase === 'testing') {
            return true;
        }

        if (str_starts_with($normalizedDatabase, 'testing_')) {
            return true;
        }

        if (str_starts_with($normalizedDatabase, 'ark_test_')) {
            return true;
        }

        if (str_ends_with($normalizedDatabase, '_test') || str_ends_with($normalizedDatabase, '_testing')) {
            return true;
        }

        // SQLite file paths used by foundry / file-backed Pest runs.
        if ($driver === 'sqlite' || str_contains($normalizedDatabase, '.sqlite')) {
            $base = basename(str_replace('\\', '/', $normalizedDatabase));
            if ($base === 'testing.sqlite' || str_starts_with($base, 'testing_') && str_ends_with($base, '.sqlite')) {
                return true;
            }
            if (str_contains($normalizedDatabase, '/testing.sqlite') || str_ends_with($normalizedDatabase, 'database/testing.sqlite')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync PHPUnit-forced $_ENV values into $_SERVER / putenv before Laravel boots.
     *
     * `php artisan test` inherits shell exports (e.g. DB_DATABASE from a migration
     * rehearsal). PHPUnit force=true updates $_ENV, but Laravel's env() may still
     * read $_SERVER from the parent process - which is how a rehearsal DB became
     * eligible for LazilyRefreshDatabase.
     */
    public static function reconcilePhpUnitEnvironmentIntoProcess(): void
    {
        foreach ([
            'APP_ENV',
            'DB_CONNECTION',
            'DB_DATABASE',
            'DB_URL',
            'DB_HOST',
            'DB_PORT',
            'DB_USERNAME',
            'DB_PASSWORD',
        ] as $key) {
            if (! array_key_exists($key, $_ENV)) {
                continue;
            }

            $value = $_ENV[$key];
            if ($value === null) {
                continue;
            }

            $string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $_SERVER[$key] = $string;
            putenv($key.'='.$string);
        }
    }

    private static function message(string $detail): string
    {
        return '[ARK test DB guard] '.$detail.
            ' Automated destructive tests may only target explicitly disposable test databases.'.
            ' Certification/rehearsal/developer databases must never be eligible.';
    }
}
