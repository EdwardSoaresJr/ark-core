<?php

use App\Ark\Install\DatabaseConnectionTester;
use App\Ark\Install\InstallerEnvironmentWriter;
use App\Ark\Install\InstallStorage;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnvironment;

test('environment and installer writes stay inside the process fixture', function () {
    $writer = app(InstallerEnvironmentWriter::class);
    expect($writer->envPath())->toBe(app()->environmentFilePath())
        ->toStartWith(TestEnvironment::root().'/environment/')
        ->not->toBe(base_path('.env'))
        ->and(InstallStorage::path('state.json'))->toStartWith(TestEnvironment::root().'/storage/');

    $writer->write(['APP_URL' => 'http://fixture.test']);
    expect(file_get_contents($writer->envPath()))->toContain('APP_URL=http://fixture.test');
});

test('Laravel rejects network and persistent connections before constructing PDO', function (array $config) {
    expect(fn () => DB::build($config)->getPdo())
        ->toThrow(RuntimeException::class, 'ARK test DB guard');
})->with([
    [['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'ark', 'username' => 'root', 'password' => 'fixture']],
    [['driver' => 'mysql', 'host' => 'production.example.test', 'database' => 'ark_test_fixture']],
    [['driver' => 'sqlite', 'database' => '/tmp/testing.sqlite']],
    [['driver' => 'sqlite', 'database' => ':memory:', 'read' => ['database' => '/tmp/testing.sqlite']]],
]);

test('unmocked installer PDO probes cannot connect during ordinary tests', function () {
    expect(fn () => app(DatabaseConnectionTester::class)->test([]))
        ->toThrow(RuntimeException::class, 'explicitly mocked');
});
