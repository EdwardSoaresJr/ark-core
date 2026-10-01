<?php

use Symfony\Component\Process\Process;

function arkParseInstallSecrets(string $path): array
{
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[$key] = trim($value, "\" \t");
    }

    return $values;
}

function arkRunInstallSecretsBootstrap(string $workDir): Process
{
    if (! is_dir($workDir.'/mysql-data')) {
        mkdir($workDir.'/mysql-data', 0755, true);
    }

    $process = new Process(
        ['/bin/sh', base_path('docker/selfhost/bootstrap-install-secrets.sh')],
        $workDir,
        [
            'ARK_INSTALL_SECRETS_FILE' => $workDir.'/install.env',
            'ARK_MYSQL_DATADIR' => $workDir.'/mysql-data',
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin:/usr/sbin:/sbin',
            'HOME' => getenv('HOME') ?: $workDir,
        ],
    );
    $process->setTimeout(15);
    $process->run();

    return $process;
}

it('generates different database passwords on two fresh bootstrap runs', function () {
    $firstDir = sys_get_temp_dir().'/ark-secrets-a-'.bin2hex(random_bytes(4));
    $secondDir = sys_get_temp_dir().'/ark-secrets-b-'.bin2hex(random_bytes(4));
    mkdir($firstDir, 0700, true);
    mkdir($secondDir, 0700, true);

    try {
        $first = arkRunInstallSecretsBootstrap($firstDir);
        $second = arkRunInstallSecretsBootstrap($secondDir);

        expect($first->isSuccessful())->toBeTrue()
            ->and($second->isSuccessful())->toBeTrue();

        $a = arkParseInstallSecrets($firstDir.'/install.env');
        $b = arkParseInstallSecrets($secondDir.'/install.env');

        expect($a['DB_PASSWORD'])->not->toBe($b['DB_PASSWORD'])
            ->and($a['MYSQL_ROOT_PASSWORD'])->not->toBe($b['MYSQL_ROOT_PASSWORD'])
            ->and($a['APP_KEY'])->not->toBe($b['APP_KEY'])
            ->and($a['REVERB_APP_SECRET'])->not->toBe($b['REVERB_APP_SECRET']);
    } finally {
        foreach ([$firstDir, $secondDir] as $dir) {
            @array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir.'/mysql-data');
            @rmdir($dir);
        }
    }
});

it('generates independent database and root passwords of sufficient length', function () {
    $dir = sys_get_temp_dir().'/ark-secrets-len-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);

    try {
        $process = arkRunInstallSecretsBootstrap($dir);
        expect($process->isSuccessful())->toBeTrue();

        $secrets = arkParseInstallSecrets($dir.'/install.env');

        expect(strlen($secrets['DB_PASSWORD']))->toBeGreaterThanOrEqual(48)
            ->and(strlen($secrets['MYSQL_ROOT_PASSWORD']))->toBeGreaterThanOrEqual(48)
            ->and($secrets['DB_PASSWORD'])->not->toBe($secrets['MYSQL_ROOT_PASSWORD'])
            ->and($secrets['DB_USERNAME'] ?? 'ark')->toBe('ark')
            ->and($secrets['DB_DATABASE'] ?? 'ark')->toBe('ark')
            ->and($secrets['APP_KEY'])->toStartWith('base64:')
            ->and(strlen($secrets['REVERB_APP_KEY']))->toBeGreaterThanOrEqual(32)
            ->and(strlen($secrets['REVERB_APP_SECRET']))->toBeGreaterThanOrEqual(48);
    } finally {
        @unlink($dir.'/install.env');
        @rmdir($dir.'/mysql-data');
        @rmdir($dir);
    }
});

it('does not print generated secrets to stdout or stderr', function () {
    $dir = sys_get_temp_dir().'/ark-secrets-quiet-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);

    try {
        $process = arkRunInstallSecretsBootstrap($dir);
        expect($process->isSuccessful())->toBeTrue();

        $secrets = arkParseInstallSecrets($dir.'/install.env');
        $output = $process->getOutput().$process->getErrorOutput();

        expect($output)->not->toContain($secrets['DB_PASSWORD'])
            ->and($output)->not->toContain($secrets['MYSQL_ROOT_PASSWORD'])
            ->and($output)->not->toContain($secrets['APP_KEY'])
            ->and($output)->not->toContain($secrets['REVERB_APP_SECRET']);
    } finally {
        @unlink($dir.'/install.env');
        @rmdir($dir.'/mysql-data');
        @rmdir($dir);
    }
});

it('preserves existing secrets on a second boot of the same installation', function () {
    $dir = sys_get_temp_dir().'/ark-secrets-keep-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);

    try {
        expect(arkRunInstallSecretsBootstrap($dir)->isSuccessful())->toBeTrue();
        $first = arkParseInstallSecrets($dir.'/install.env');

        mkdir($dir.'/mysql-data/mysql', 0755, true);

        expect(arkRunInstallSecretsBootstrap($dir)->isSuccessful())->toBeTrue();
        $second = arkParseInstallSecrets($dir.'/install.env');

        expect($second['DB_PASSWORD'])->toBe($first['DB_PASSWORD'])
            ->and($second['MYSQL_ROOT_PASSWORD'])->toBe($first['MYSQL_ROOT_PASSWORD'])
            ->and($second['APP_KEY'])->toBe($first['APP_KEY']);
    } finally {
        @unlink($dir.'/install.env');
        @array_map('unlink', glob($dir.'/mysql-data/mysql/*') ?: []);
        @rmdir($dir.'/mysql-data/mysql');
        @rmdir($dir.'/mysql-data');
        @rmdir($dir);
    }
});

it('creates new secrets after a fresh reset of the secrets file', function () {
    $dir = sys_get_temp_dir().'/ark-secrets-reset-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);

    try {
        expect(arkRunInstallSecretsBootstrap($dir)->isSuccessful())->toBeTrue();
        $first = arkParseInstallSecrets($dir.'/install.env');
        unlink($dir.'/install.env');

        expect(arkRunInstallSecretsBootstrap($dir)->isSuccessful())->toBeTrue();
        $second = arkParseInstallSecrets($dir.'/install.env');

        expect($second['DB_PASSWORD'])->not->toBe($first['DB_PASSWORD'])
            ->and($second['MYSQL_ROOT_PASSWORD'])->not->toBe($first['MYSQL_ROOT_PASSWORD']);
    } finally {
        @unlink($dir.'/install.env');
        @rmdir($dir.'/mysql-data');
        @rmdir($dir);
    }
});

it('refuses to invent database passwords when mysql data already exists', function () {
    $dir = sys_get_temp_dir().'/ark-secrets-existing-'.bin2hex(random_bytes(4));
    mkdir($dir.'/mysql-data/mysql', 0755, true);

    try {
        $process = arkRunInstallSecretsBootstrap($dir);
        expect($process->isSuccessful())->toBeFalse()
            ->and(is_file($dir.'/install.env'))->toBeFalse();
    } finally {
        @rmdir($dir.'/mysql-data/mysql');
        @rmdir($dir.'/mysql-data');
        @rmdir($dir);
    }
});
