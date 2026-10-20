<?php

namespace App\Ark\Runtime;

use Illuminate\Support\Facades\DB;

/**
 * Identifies the database the current process is reading.
 * Repair-order memory is keyed by this so one worker cannot serve another shop's rows.
 */
final class DatabaseReadScope
{
    public static function key(): string
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        return implode("\0", [
            (string) $connection->getName(),
            (string) ($config['host'] ?? ''),
            (string) ($config['port'] ?? ''),
            (string) $connection->getDatabaseName(),
            (string) ($config['username'] ?? ''),
        ]);
    }
}
