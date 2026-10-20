<?php

namespace Tests\Support;

use Illuminate\Database\Connectors\ConnectionFactory;
use RuntimeException;

/** Validates before Laravel can construct a PDO, including read/write connections. */
final class GuardedConnectionFactory extends ConnectionFactory
{
    public function __construct($container, private readonly ?array $mysql = null)
    {
        parent::__construct($container);
    }

    public function make(array $config, $name = null)
    {
        self::assertSafe($config, $this->mysql);

        return parent::make($config, $name);
    }

    public static function assertSafe(array $config, ?array $mysql = null): void
    {
        foreach (['url', 'read', 'write', 'direct', 'unix_socket'] as $key) {
            if (! empty($config[$key])) {
                throw new RuntimeException('[ARK test DB guard] Alternate database endpoints are forbidden.');
            }
        }

        if (($config['driver'] ?? null) === 'sqlite' && ($config['database'] ?? null) === ':memory:') {
            return;
        }

        if ($mysql !== null && ($config['driver'] ?? null) === 'mysql') {
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                if ((string) ($config[$key] ?? '') !== (string) $mysql[$key]) {
                    throw new RuntimeException('[ARK test DB guard] MySQL connection does not match the owned test database.');
                }
            }

            return;
        }

        throw new RuntimeException('[ARK test DB guard] Ordinary tests may connect only to SQLite :memory:.');
    }
}
