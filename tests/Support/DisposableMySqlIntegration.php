<?php

namespace Tests\Support;

use Closure;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Explicit local integration configuration. No DB_* fallback and no reused databases. */
final class DisposableMySqlIntegration
{
    public static function configuration(array $environment): array
    {
        if (($environment['ARK_TEST_MYSQL'] ?? null) !== '1') {
            throw new RuntimeException('[ARK test DB guard] MySQL integration requires ARK_TEST_MYSQL=1.');
        }
        $keys = ['host', 'port', 'username', 'password', 'database_prefix'];
        $config = [];
        foreach ($keys as $key) {
            $value = $environment['ARK_TEST_MYSQL_'.strtoupper($key)] ?? '';
            if (! is_string($value) || $value === '') {
                throw new RuntimeException('[ARK test DB guard] Explicit disposable MySQL configuration is required: '.$key.'.');
            }
            $config[$key] = $value;
        }
        if (! in_array($config['host'], ['127.0.0.1', '::1'], true)
            || ! ctype_digit($config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535
            || preg_match('/\Aark_test_[a-z0-9_]{1,20}\z/', $config['database_prefix']) !== 1
            || preg_match('/\Aark_test_[a-z0-9_]{1,20}\z/', $config['username']) !== 1) {
            throw new RuntimeException('[ARK test DB guard] Use a loopback host, explicit port, ark_test_* account and database prefix.');
        }

        return $config;
    }

    public static function subprocessEnvironment(string $context): array
    {
        return [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'TMPDIR' => realpath(sys_get_temp_dir()),
            'APP_ENV' => 'testing',
            'ARK_TEST_MYSQL_CONTEXT' => $context,
        ];
    }

    /** Only contexts created after this runner successfully CREATEs a fresh database are usable. */
    public static function helperConfiguration(): array
    {
        $path = getenv('ARK_TEST_MYSQL_CONTEXT') ?: '';
        $temp = preg_quote(realpath(sys_get_temp_dir()), '#');
        if (preg_match('#\A'.$temp.'/ark-core-tests-[0-9]+-[a-f0-9]{24}/mysql-[a-f0-9]{24}\.json\z#', $path) !== 1
            || is_link($path) || ! is_file($path) || realpath($path) !== $path) {
            throw new RuntimeException('[ARK test DB guard] A runner-owned MySQL context is required; direct helper execution is forbidden.');
        }
        $context = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $config = self::configuration($context['environment'] ?? []);
        $nonce = $context['nonce'] ?? '';
        if (! is_string($nonce) || preg_match('/\A[a-f0-9]{24}\z/', $nonce) !== 1
            || basename($path) !== 'mysql-'.$nonce.'.json'
            || ($context['created'] ?? false) !== true) {
            throw new RuntimeException('[ARK test DB guard] Invalid owned MySQL database context.');
        }
        $config['database'] = $config['database_prefix'].'_'.$nonce;
        AssertDisposableTestDatabase::abortUnlessSafe('testing', 'mysql', $config['database'], 'mysql');

        return $config;
    }

    public static function run(string $helper, ?Closure $connect = null, ?Closure $execute = null): array
    {
        $environment = getenv();
        $config = self::configuration($environment);
        $nonce = bin2hex(random_bytes(12));
        $database = $config['database_prefix'].'_'.$nonce;
        AssertDisposableTestDatabase::abortUnlessSafe('testing', 'mysql', $database, 'mysql');
        $context = TestEnvironment::root().'/mysql-'.$nonce.'.json';
        $connect ??= static fn (array $settings): PDO => new PDO(
            'mysql:host='.$settings['host'].';port='.$settings['port'].';charset=utf8mb4',
            $settings['username'], $settings['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
        $server = $connect($config);
        // A collision fails. Never attach to or drop a pre-existing database.
        $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $explicit = array_intersect_key($environment, array_flip([
                'ARK_TEST_MYSQL', 'ARK_TEST_MYSQL_HOST', 'ARK_TEST_MYSQL_PORT',
                'ARK_TEST_MYSQL_USERNAME', 'ARK_TEST_MYSQL_PASSWORD', 'ARK_TEST_MYSQL_DATABASE_PREFIX',
            ]));
            file_put_contents($context, json_encode([
                'environment' => $explicit, 'nonce' => $nonce, 'created' => true,
            ], JSON_THROW_ON_ERROR), LOCK_EX);
            chmod($context, 0600);
            // Symfony merges the parent environment unless inherited keys are explicitly removed.
            $env = array_fill_keys(array_unique(array_merge(array_keys($environment), array_keys($_ENV), array_keys($_SERVER))), false);
            $env = array_merge($env, self::subprocessEnvironment($context));
            $process = new Process([PHP_BINARY, $helper, 'parent'], dirname(__DIR__, 2), $env);
            $process->setTimeout(120);
            $execute ??= static function (Process $process): string {
                $process->mustRun();

                return $process->getOutput();
            };

            return json_decode($execute($process), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            @unlink($context);
            $server->exec('DROP DATABASE `'.$database.'`');
        }
    }
}
