<?php

namespace App\Ark\Install;

use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Throwable;

class DatabaseConnectionTester
{
    /**
     * @param  array{connection?: string, host: string, port: string|int, database: string, username: string, password?: string|null}  $config
     * @return array{ok: bool, message: string}
     */
    public function test(array $config): array
    {
        $connection = $config['connection'] ?? 'mysql';
        if ($connection !== 'mysql') {
            return ['ok' => false, 'message' => 'ARK currently supports MySQL for installation.'];
        }

        $host = trim((string) $config['host']);
        $port = (int) ($config['port'] ?: 3306);
        $database = trim((string) $config['database']);
        $username = (string) $config['username'];
        $password = (string) ($config['password'] ?? '');

        if ($host === '' || $database === '' || $username === '') {
            return ['ok' => false, 'message' => 'Host, database name, and username are required.'];
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');

            return ['ok' => true, 'message' => 'Database connection successful.'];
        } catch (PDOException $e) {
            Log::warning('installer.database.test_failed', [
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'code' => $e->getCode(),
            ]);

            return ['ok' => false, 'message' => $this->safeMessage($e)];
        } catch (Throwable $e) {
            Log::warning('installer.database.test_failed', ['error' => $e::class]);

            return ['ok' => false, 'message' => 'Could not connect to the database.'];
        }
    }

    private function safeMessage(PDOException $e): string
    {
        $msg = $e->getMessage();
        // Strip password if driver ever echoed DSN with credentials.
        $msg = preg_replace('/password=[^;\s]+/i', 'password=***', $msg) ?? $msg;

        if (str_contains($msg, 'Access denied')) {
            return 'Access denied — check username and password.';
        }
        if (str_contains($msg, 'Unknown database')) {
            return 'Database does not exist — create an empty MySQL database first.';
        }
        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'timed out')) {
            return 'Could not reach the database host — check host and port.';
        }

        return 'Database connection failed. Verify host, port, database name, and credentials.';
    }
}
