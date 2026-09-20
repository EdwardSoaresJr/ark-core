<?php

use App\Ark\Install\DatabaseConnectionTester;
use App\Ark\Install\DatabaseSafetyInspector;
use App\Ark\Install\InstallDraft;
use App\Ark\Install\InstallationState;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    InstallationState::resetForTests();
    InstallDraft::clear();
    session()->forget('install.db_password');
    $this->originalDefault = config('database.default');
    $this->originalMysql = config('database.connections.mysql');
});

afterEach(function () {
    config([
        'database.default' => $this->originalDefault ?? 'sqlite',
        'database.connections.mysql' => $this->originalMysql,
        'install.managed_database' => false,
    ]);
    InstallationState::resetForTests();
    InstallDraft::clear();
    session()->forget('install.db_password');
});

/**
 * @return array{0: string, 1: ?string}
 */
function arkFreezeInstallerEnvMode(string $mode): array
{
    $path = base_path('.env');
    $original = is_file($path) ? File::get($path) : null;
    if (! is_file($path)) {
        File::put($path, "APP_KEY=\n");
    }
    expect(@chmod($path, $mode === 'immutable' ? 0444 : 0600))->toBeTrue();

    return [$path, $original];
}

function arkRestoreInstallerEnvFile(string $path, ?string $original): void
{
    @chmod($path, 0600);
    if ($original === null) {
        File::delete($path);
    } else {
        File::put($path, $original);
    }
}

it('surfaces runtime Docker DB settings on the database step without exposing the password', function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.host' => 'mysql',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => 'ark',
        'database.connections.mysql.password' => 'secret-test-value',
    ]);
    [$path, $original] = arkFreezeInstallerEnvMode('immutable');

    try {
        $html = $this->get(route('install.database'))
            ->assertOk()
            ->assertSee('value="mysql"', false)
            ->assertSee('value="3306"', false)
            ->assertSee('value="ark"', false)
            ->assertDontSee('secret-test-value')
            ->assertSee('Already configured on this host', false)
            ->getContent();

        expect($html)->not->toContain('value="127.0.0.1"')
            ->and($html)->toMatch('/id="db_password"[^>]*value=""/')
            ->and($html)->not->toContain('secret-test-value');
    } finally {
        config([
            'database.default' => $this->originalDefault,
            'database.connections.mysql' => $this->originalMysql,
        ]);
        arkRestoreInstallerEnvFile($path, $original);
    }
});

it('shows a connected database state for managed compose without exposing the password', function () {
    $password = 'runtime-secret-value-'.bin2hex(random_bytes(6));
    config([
        'install.managed_database' => true,
        'database.default' => 'mysql',
        'database.connections.mysql.host' => 'mysql',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => 'ark',
        'database.connections.mysql.password' => $password,
    ]);

    $this->mock(DatabaseConnectionTester::class, function ($mock) {
        $mock->shouldReceive('test')->andReturn(['ok' => true, 'message' => 'Database connection successful.']);
    });
    $this->mock(DatabaseSafetyInspector::class, function ($mock) {
        $mock->shouldReceive('inspect')->andReturn([
            'ok' => true,
            'verdict' => 'empty',
            'message' => 'Database is empty and ready for ARK.',
        ]);
    });

    [$path, $original] = arkFreezeInstallerEnvMode('immutable');

    try {
        $html = $this->get(route('install.database'))
            ->assertOk()
            ->assertSee('Connected', false)
            ->assertDontSee($password)
            ->assertDontSee('id="db_password"', false)
            ->assertDontSee('name="db_password"', false)
            ->getContent();

        expect($html)->not->toContain($password);
    } finally {
        config([
            'install.managed_database' => false,
            'database.default' => $this->originalDefault,
            'database.connections.mysql' => $this->originalMysql,
        ]);
        arkRestoreInstallerEnvFile($path, $original);
    }
});

it('lets managed compose continue without the operator knowing the generated password', function () {
    $password = 'runtime-secret-value-'.bin2hex(random_bytes(6));
    config([
        'install.managed_database' => true,
        'database.default' => 'mysql',
        'database.connections.mysql.host' => 'mysql',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => 'ark',
        'database.connections.mysql.password' => $password,
    ]);

    $this->mock(DatabaseConnectionTester::class, function ($mock) {
        $mock->shouldReceive('test')->andReturn(['ok' => true, 'message' => 'Database connection successful.']);
    });
    $this->mock(DatabaseSafetyInspector::class, function ($mock) {
        $mock->shouldReceive('inspect')->andReturn([
            'ok' => true,
            'verdict' => 'empty',
            'message' => 'Database is empty and ready for ARK.',
        ]);
    });

    [$path, $original] = arkFreezeInstallerEnvMode('immutable');

    try {
        $this->from(route('install.database'))
            ->post(route('install.database.test'), [
                'app_url' => 'http://localhost:8088',
            ])
            ->assertRedirect(route('install.shop'));

        expect(session('install.db_password'))->toBe($password)
            ->and(InstallDraft::all()['db_managed'] ?? false)->toBeTrue()
            ->and(InstallDraft::all()['db_tested'] ?? false)->toBeTrue();

        $this->get(route('install.shop'))
            ->assertOk()
            ->assertDontSee($password);
    } finally {
        config([
            'install.managed_database' => false,
            'database.default' => $this->originalDefault,
            'database.connections.mysql' => $this->originalMysql,
        ]);
        arkRestoreInstallerEnvFile($path, $original);
        session()->forget('install.db_password');
        InstallDraft::clear();
    }
});

it('keeps writable-host localhost defaults when runtime still points at 127.0.0.1', function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => '',
        'database.connections.mysql.password' => '',
    ]);
    [$path, $original] = arkFreezeInstallerEnvMode('writable');

    try {
        $this->get(route('install.database'))
            ->assertOk()
            ->assertSee('value="127.0.0.1"', false)
            ->assertDontSee('Already configured on this host');
    } finally {
        config([
            'database.default' => $this->originalDefault,
            'database.connections.mysql' => $this->originalMysql,
        ]);
        arkRestoreInstallerEnvFile($path, $original);
    }
});
