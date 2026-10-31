<?php

use Symfony\Component\Process\Process;

test('the parallel runner builds its application in the isolated environment', function () {
    $code = 'require '.var_export(dirname(__DIR__).'/bootstrap.php', true).';'
        .'$app = (new class { use Tests\\CreatesApplication; })->createApplication();'
        .'$connection = config("database.connections.".config("database.default"));'
        .'echo json_encode(["env" => $app->environmentFilePath(), "root" => Tests\\Support\\TestEnvironment::root(),'
        .' "bytes" => filesize($app->environmentFilePath()), "database" => $connection["database"]]);';

    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2));
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['env'])->toBe($result['root'].'/environment/.env')
        ->and($result['bytes'])->toBe(0)
        ->and($result['database'])->toBe(':memory:');
});

test('bin/pest runs without an explicit configuration', function () {
    $process = new Process([PHP_BINARY, 'bin/pest', '--list-tests', 'tests/Unit/ExampleTest.php'], dirname(__DIR__, 2));
    $process->mustRun();

    expect($process->getOutput())->toContain('ExampleTest');
});
