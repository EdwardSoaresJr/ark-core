<?php

use Tests\Support\DisposableMySqlIntegration;
use Tests\Support\GuardedConnectionFactory;
use Tests\Support\TestEnvironment;

test('ordinary database policy rejects every persistent or network target', function (array $config) {
    expect(fn () => GuardedConnectionFactory::assertSafe($config))
        ->toThrow(RuntimeException::class, 'ARK test DB guard');
})->with([
    'developer mysql' => [['driver' => 'mysql', 'database' => 'ark']],
    'test named mysql without opt in' => [['driver' => 'mysql', 'database' => 'ark_test_safe']],
    'sqlite developer file' => [['driver' => 'sqlite', 'database' => '/tmp/developer.sqlite']],
    'sqlite test named file' => [['driver' => 'sqlite', 'database' => '/tmp/testing.sqlite']],
    'pgsql' => [['driver' => 'pgsql', 'database' => 'testing']],
    'sqlsrv' => [['driver' => 'sqlsrv', 'database' => 'testing']],
    'missing driver' => [['database' => ':memory:']],
    'url override' => [['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'mysql://host/ark']],
    'read endpoint' => [['driver' => 'sqlite', 'database' => ':memory:', 'read' => ['host' => 'production']]],
    'write endpoint' => [['driver' => 'sqlite', 'database' => ':memory:', 'write' => ['host' => 'production']]],
    'direct endpoint' => [['driver' => 'sqlite', 'database' => ':memory:', 'direct' => ['host' => 'production']]],
    'socket endpoint' => [['driver' => 'sqlite', 'database' => ':memory:', 'unix_socket' => '/tmp/mysql.sock']],
]);

test('ordinary database policy permits memory only', function () {
    GuardedConnectionFactory::assertSafe(['driver' => 'sqlite', 'database' => ':memory:']);
    expect(true)->toBeTrue();
});

function disposableMySqlPolicyFixture(): array
{
    return [
        'ARK_TEST_MYSQL' => '1', 'ARK_TEST_MYSQL_HOST' => '127.0.0.1',
        'ARK_TEST_MYSQL_PORT' => '13306', 'ARK_TEST_MYSQL_USERNAME' => 'ark_test_runner',
        'ARK_TEST_MYSQL_PASSWORD' => 'fixture-only', 'ARK_TEST_MYSQL_DATABASE_PREFIX' => 'ark_test_overlap',
    ];
}

test('integration policy fails closed before connecting', function (string $key, ?string $value) {
    $environment = disposableMySqlPolicyFixture();
    if ($value === null) {
        unset($environment[$key]);
    } else {
        $environment[$key] = $value;
    }
    expect(fn () => DisposableMySqlIntegration::configuration($environment))
        ->toThrow(RuntimeException::class, 'ARK test DB guard');
})->with([
    ['ARK_TEST_MYSQL', null], ['ARK_TEST_MYSQL', '0'],
    ['ARK_TEST_MYSQL_HOST', 'production.example.test'], ['ARK_TEST_MYSQL_HOST', 'localhost'],
    ['ARK_TEST_MYSQL_PORT', ''], ['ARK_TEST_MYSQL_PORT', '0'], ['ARK_TEST_MYSQL_PORT', '65536'],
    ['ARK_TEST_MYSQL_USERNAME', 'root'], ['ARK_TEST_MYSQL_USERNAME', 'ark'],
    ['ARK_TEST_MYSQL_PASSWORD', ''], ['ARK_TEST_MYSQL_PASSWORD', null],
    ['ARK_TEST_MYSQL_DATABASE_PREFIX', 'ark'], ['ARK_TEST_MYSQL_DATABASE_PREFIX', 'production_test'],
    ['ARK_TEST_MYSQL_DATABASE_PREFIX', 'ark_test_x`; DROP DATABASE ark; --'],
]);

test('integration policy accepts explicitly disposable loopback configuration', function () {
    expect(DisposableMySqlIntegration::configuration(disposableMySqlPolicyFixture()))
        ->toHaveKey('database_prefix', 'ark_test_overlap');
});

test('ordinary process initialization overwrites polluted application credentials', function () {
    foreach (['APP_ENV' => 'production', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'production_shop',
        'DB_URL' => 'mysql://unsafe', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => 'fixture-only',
        'DATABASE_URL' => 'mysql://unsafe', 'MYSQL_ROOT_PASSWORD' => 'fixture-only', 'PGPASSWORD' => 'fixture-only'] as $key => $value) {
        TestEnvironment::set($key, $value);
    }
    TestEnvironment::initialize();
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'DB_URL' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => '',
        'DATABASE_URL' => '', 'MYSQL_ROOT_PASSWORD' => '', 'PGPASSWORD' => ''] as $key => $value) {
        expect(getenv($key))->toBe($value)
            ->and($_ENV[$key])->toBe($value)
            ->and($_SERVER[$key])->toBe($value);
    }
});

test('integration subprocess environment never inherits application credentials', function () {
    $environment = DisposableMySqlIntegration::subprocessEnvironment('/fixture/context.json');
    expect($environment)->not->toHaveKeys(['DB_HOST', 'DB_PASSWORD', 'DB_DATABASE', 'DB_URL', 'HOME'])
        ->and($environment['APP_ENV'])->toBe('testing');
});

test('a helper cannot be executed directly or pointed at a checkout env file', function () {
    $original = getenv('ARK_TEST_MYSQL_CONTEXT');
    try {
        foreach (['', dirname(__DIR__, 2).'/.env'] as $path) {
            putenv('ARK_TEST_MYSQL_CONTEXT='.$path);
            expect(fn () => DisposableMySqlIntegration::helperConfiguration())
                ->toThrow(RuntimeException::class, 'runner-owned');
        }
    } finally {
        putenv($original === false ? 'ARK_TEST_MYSQL_CONTEXT' : 'ARK_TEST_MYSQL_CONTEXT='.$original);
    }
});

test('both standalone helpers fail before Laravel boot without an owned context', function () {
    foreach (['repair_order_concurrency_overlap.php', 'financial_submission_intent_overlap.php'] as $helper) {
        $environment = array_fill_keys(array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER))), false);
        $environment['ARK_TEST_MYSQL_CONTEXT'] = '';
        $process = new Symfony\Component\Process\Process([PHP_BINARY, dirname(__DIR__).'/Support/'.$helper, 'parent'], null, $environment);
        $process->run();
        expect($process->getExitCode())->toBe(255)
            ->and($process->getErrorOutput().$process->getOutput())->toContain('runner-owned MySQL context');
    }
});

test('two independent processes have different filesystem roots and discard fixtures on exit', function () {
    $code = 'require '.var_export(dirname(__DIR__).'/bootstrap.php', true).'; echo Tests\\Support\\TestEnvironment::root();';
    $roots = [];
    for ($i = 0; $i < 2; $i++) {
        $process = new Symfony\Component\Process\Process([PHP_BINARY, '-r', $code]);
        $process->mustRun();
        $roots[] = $process->getOutput();
        expect(is_dir($roots[$i]))->toBeFalse();
    }
    expect($roots[0])->not->toBe($roots[1])->not->toBe(TestEnvironment::root());
});

function withDisposableMySqlPolicyFixture(Closure $test): void
{
    $original = [];
    foreach (disposableMySqlPolicyFixture() as $key => $value) {
        $original[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        TestEnvironment::set($key, $value);
    }
    try {
        $test();
    } finally {
        foreach ($original as $key => [$process, $env, $server]) {
            putenv($process === false ? $key : $key.'='.$process);
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
        }
    }
}

function recordingTestPdo(bool $collision = false): PDO
{
    return new class($collision) extends PDO
    {
        public array $statements = [];

        public function __construct(private bool $collision) {}

        public function exec(string $statement): int|false
        {
            $this->statements[] = $statement;
            if ($this->collision && str_starts_with($statement, 'CREATE DATABASE')) {
                throw new RuntimeException('Database already exists.');
            }

            return 0;
        }
    };
}

test('integration lifecycle owns fresh targets and sanitizes the actual process environment', function () {
    withDisposableMySqlPolicyFixture(function () {
        $server = recordingTestPdo();
        $databases = [];
        $paths = [];
        for ($i = 0; $i < 2; $i++) {
            $result = DisposableMySqlIntegration::run('fixture-helper', fn () => $server, function ($process) use (&$databases, &$paths) {
                $environment = $process->getEnv();
                expect($environment['DB_PASSWORD'])->toBeFalse()
                    ->and($environment['DB_DATABASE'])->toBeFalse()
                    ->and($environment['APP_ENV'])->toBe('testing');
                $path = $environment['ARK_TEST_MYSQL_CONTEXT'];
                $paths[] = $path;
                $context = json_decode(file_get_contents($path), true);
                $database = $context['environment']['ARK_TEST_MYSQL_DATABASE_PREFIX'].'_'.$context['nonce'];
                $databases[] = $database;
                expect($context['created'])->toBeTrue()
                    ->and(fileperms($path) & 0777)->toBe(0600);

                return '{"fixture":true}';
            });
            expect($result)->toBe(['fixture' => true]);
        }
        expect($databases[0])->not->toBe($databases[1]);
        foreach ($databases as $i => $database) {
            expect($server->statements[$i * 2])->toBe('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
                ->and($server->statements[$i * 2 + 1])->toBe('DROP DATABASE `'.$database.'`')
                ->and(file_exists($paths[$i]))->toBeFalse();
        }
    });
});

test('integration lifecycle never drops or uses a database after a create collision', function () {
    withDisposableMySqlPolicyFixture(function () {
        $server = recordingTestPdo(collision: true);
        expect(fn () => DisposableMySqlIntegration::run('fixture-helper', fn () => $server,
            fn () => throw new RuntimeException('Helper must not run.')))
            ->toThrow(RuntimeException::class, 'Database already exists.');
        expect($server->statements)->toHaveCount(1);
    });
});

test('integration lifecycle drops only its owned target after a helper failure', function () {
    withDisposableMySqlPolicyFixture(function () {
        $server = recordingTestPdo();
        expect(fn () => DisposableMySqlIntegration::run('fixture-helper', fn () => $server,
            fn () => throw new RuntimeException('Helper failed.')))
            ->toThrow(RuntimeException::class, 'Helper failed.');
        expect($server->statements)->toHaveCount(2)
            ->and($server->statements[1])->toStartWith('DROP DATABASE `ark_test_overlap_');
    });
});
