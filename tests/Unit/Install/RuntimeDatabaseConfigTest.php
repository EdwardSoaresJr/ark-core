<?php

use App\Ark\Install\RuntimeDatabaseConfig;

beforeEach(function () {
    $this->originalDefault = config('database.default');
    $this->originalMysql = config('database.connections.mysql');
});

afterEach(function () {
    config([
        'database.default' => $this->originalDefault,
        'database.connections.mysql' => $this->originalMysql,
        'install.managed_database' => false,
    ]);
});

function arkUnitDockerDbConfig(string $password = 'secret-test-value'): void
{
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.host' => 'mysql',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => 'ark',
        'database.connections.mysql.password' => $password,
    ]);
}

it('reads the active Laravel database connection as runtime config', function () {
    arkUnitDockerDbConfig('secret-test-value');

    $runtime = RuntimeDatabaseConfig::read();

    expect($runtime['host'])->toBe('mysql')
        ->and($runtime['port'])->toBe(3306)
        ->and($runtime['database'])->toBe('ark')
        ->and($runtime['username'])->toBe('ark')
        ->and($runtime['password'])->toBe('secret-test-value');
});

it('prefers draft values over runtime for non-secret form defaults', function () {
    arkUnitDockerDbConfig();

    $defaults = RuntimeDatabaseConfig::formDefaults([
        'db_host' => 'db.internal',
        'db_username' => 'custom',
    ]);

    expect($defaults['db_host'])->toBe('db.internal')
        ->and($defaults['db_username'])->toBe('custom')
        ->and($defaults['db_database'])->toBe('ark')
        ->and($defaults['db_port'])->toBe(3306)
        ->and($defaults['runtime_password_configured'])->toBeTrue();
});

it('resolves a blank password to the runtime password when identity matches', function () {
    arkUnitDockerDbConfig('secret-test-value');

    expect(RuntimeDatabaseConfig::resolvePassword('', [
        'host' => 'mysql',
        'port' => 3306,
        'database' => 'ark',
        'username' => 'ark',
    ]))->toBe('secret-test-value');
});

it('does not use the runtime password when host identity changes', function () {
    arkUnitDockerDbConfig('secret-test-value');

    expect(RuntimeDatabaseConfig::resolvePassword('', [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'ark',
        'username' => 'ark',
    ]))->toBe('');
});

it('lets an explicit password win over the runtime password', function () {
    arkUnitDockerDbConfig('secret-test-value');

    expect(RuntimeDatabaseConfig::resolvePassword('operator-override', [
        'host' => 'mysql',
        'port' => 3306,
        'database' => 'ark',
        'username' => 'ark',
    ]))->toBe('operator-override');
});

it('treats compose runtime as a managed database when the flag is set', function () {
    arkUnitDockerDbConfig('secret-test-value');
    config(['install.managed_database' => true]);

    expect(RuntimeDatabaseConfig::isManaged())->toBeTrue();
});

it('does not treat a manual php install as managed', function () {
    config([
        'install.managed_database' => false,
        'database.default' => 'mysql',
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.database' => 'ark',
        'database.connections.mysql.username' => 'ark',
        'database.connections.mysql.password' => 'secret-test-value',
    ]);

    expect(RuntimeDatabaseConfig::isManaged())->toBeFalse();
});

