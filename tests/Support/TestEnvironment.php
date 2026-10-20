<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Process-owned filesystem and environment; never loads the checkout's .env. */
final class TestEnvironment
{
    private static ?string $root = null;

    public static function initialize(?array $mysql = null): void
    {
        // Clear alternate application/driver credential variables as well as DB_*.
        foreach (array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER))) as $key) {
            if (preg_match('/\A(?:DB_|MYSQL_|MARIADB_|ARKSMS_LEGACY_|DATABASE_URL\z|PG(?:HOST|PORT|DATABASE|USER|PASSWORD)\z)/', $key) === 1) {
                self::set($key, '');
            }
        }
        if (self::$root === null) {
            $root = realpath(sys_get_temp_dir()).'/ark-core-tests-'.getmypid().'-'.bin2hex(random_bytes(12));
            if (! mkdir($root, 0700)) {
                throw new RuntimeException('Could not create a fresh isolated test filesystem.');
            }
            self::$root = $root;
            register_shutdown_function(self::cleanup(...));
            foreach (['environment', 'storage/app/install/testing', 'storage/app/private', 'storage/app/public',
                'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views',
                'storage/logs', 'cache'] as $directory) {
                if (! mkdir(self::$root.'/'.$directory, 0700, true) && ! is_dir(self::$root.'/'.$directory)) {
                    throw new RuntimeException('Could not create the isolated test filesystem.');
                }
            }
        }

        $values = [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'DB_HOST' => '', 'DB_PORT' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => '', 'DB_SOCKET' => '',
            'ARKSMS_LEGACY_HOST' => '', 'ARKSMS_LEGACY_USERNAME' => '', 'ARKSMS_LEGACY_PASSWORD' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array',
            'DRAGON_PROVIDER' => 'fake', 'FCM_ENABLED' => 'false', 'FIREBASE_CREDENTIALS' => '',
            'COOLIFY_ENABLED' => 'false',
            'ARK_SHARED_SECRETS_PATH' => self::$root.'/environment/ark-production.env',
            'ARK_PLATFORM_BASE_URL' => '', 'ARK_CLOUD_BASE_URL' => '', 'ARK_MAIL_SERVICE_URL' => '',
            'APP_CONFIG_CACHE' => self::$root.'/cache/config.php',
            'APP_ROUTES_CACHE' => self::$root.'/cache/routes.php',
            'APP_EVENTS_CACHE' => self::$root.'/cache/events.php',
        ];
        if ($mysql !== null) {
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                $values['DB_'.strtoupper($key)] = (string) $mysql[$key];
            }
            $values['DB_CONNECTION'] = 'mysql';
        }
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
        AssertDisposableTestDatabase::reconcilePhpUnitEnvironmentIntoProcess();
        AssertDisposableTestDatabase::abortUnlessSafe('testing', $values['DB_CONNECTION'], $values['DB_DATABASE']);
    }

    public static function configureApplication(Application $app, ?array $mysql = null): void
    {
        $app->useEnvironmentPath(self::root().'/environment');
        $app->loadEnvironmentFrom('.env');
        $app->useStoragePath(self::root().'/storage');
        $app->extend('db.factory', fn ($factory, $container) => new GuardedConnectionFactory($container, $mysql));
        $app->bind(\App\Ark\Install\DatabaseConnectionTester::class, fn () => new class extends \App\Ark\Install\DatabaseConnectionTester
        {
            public function test(array $config): array
            {
                throw new RuntimeException('[ARK test DB guard] Installer MySQL probes must be explicitly mocked in ordinary tests.');
            }
        });
        $app->bind(\App\Ark\Install\DatabaseSafetyInspector::class, fn () => new class extends \App\Ark\Install\DatabaseSafetyInspector
        {
            public function inspect(array $config): array
            {
                throw new RuntimeException('[ARK test DB guard] Installer MySQL probes must be explicitly mocked in ordinary tests.');
            }
        });
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($mysql): void {
            if (! $app->environment('testing')) {
                throw new RuntimeException('[ARK test DB guard] The test application must be in testing.');
            }
            GuardedConnectionFactory::assertSafe($app['config']->get('database.connections.'.$app['config']->get('database.default')), $mysql);
        });
        $app->booted(fn () => Http::preventStrayRequests());
    }

    public static function root(): string
    {
        if (self::$root === null) {
            self::initialize();
        }

        return self::$root;
    }

    public static function set(string $key, string $value): void
    {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    private static function cleanup(): void
    {
        if (self::$root === null || ! is_dir(self::$root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir(self::$root);
    }
}
