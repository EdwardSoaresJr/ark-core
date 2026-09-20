<?php

namespace App\Ark\Install;

use PDO;
use PDOException;

/**
 * Fail closed on suspicious non-empty databases before migrations.
 */
class DatabaseSafetyInspector
{
    /**
     * @param  array{host: string, port: string|int, database: string, username: string, password?: string|null}  $config
     * @return array{ok: bool, verdict: 'empty'|'ark'|'suspicious'|'error', message: string}
     */
    public function inspect(array $config): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                trim($config['host']),
                (int) ($config['port'] ?: 3306),
                trim($config['database']),
            );
            $pdo = new PDO($dsn, (string) $config['username'], (string) ($config['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($tables === [] || $tables === false) {
                return [
                    'ok' => true,
                    'verdict' => 'empty',
                    'message' => 'Database is empty and ready for ARK.',
                ];
            }

            $names = array_map('strval', $tables);
            $hasMigrations = in_array('migrations', $names, true);
            $hasUsers = in_array('users', $names, true);
            $hasShopSettings = in_array('shop_settings', $names, true);

            if ($hasMigrations && ($hasUsers || $hasShopSettings)) {
                return [
                    'ok' => false,
                    'verdict' => 'ark',
                    'message' => 'This database already looks like an ARK installation. The installer will not migrate or overwrite it. Point ARK at an empty database, or restore from backup using operator procedures.',
                ];
            }

            return [
                'ok' => false,
                'verdict' => 'suspicious',
                'message' => 'This database is not empty and does not look like a compatible ARK schema. Choose an empty database. The installer will not overwrite existing data.',
            ];
        } catch (PDOException $e) {
            return [
                'ok' => false,
                'verdict' => 'error',
                'message' => 'Could not inspect the database safely.',
            ];
        }
    }
}
