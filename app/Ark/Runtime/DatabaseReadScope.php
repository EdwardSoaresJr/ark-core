<?php

namespace App\Ark\Runtime;

use Illuminate\Support\Facades\DB;

/**
 * Identifies the database the current process is reading.
 * Repair-order memory and shop-settings schema memory are keyed by this so a
 * worker that changes shops cannot reuse another shop's rows or columns.
 * The connection name alone is not the identity: host, port, database, and
 * username are included. Two :memory: connections stay distinct because their
 * names differ, and two shops on one host stay distinct because their database
 * names differ.
 */
final class DatabaseReadScope
{
    public static function key(): string
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        return implode("\0", [
            (string) $connection->getName(),
            (string) ($config['driver'] ?? $connection->getDriverName()),
            (string) ($config['host'] ?? ''),
            (string) ($config['port'] ?? ''),
            (string) $connection->getDatabaseName(),
            (string) ($config['username'] ?? ''),
        ]);
    }
}
