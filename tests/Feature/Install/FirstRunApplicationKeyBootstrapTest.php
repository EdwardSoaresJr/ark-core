<?php

use App\Ark\Install\EnsureFirstRunApplicationKey;
use App\Ark\Install\InstallationState;
use App\Ark\Install\InstallerEnvironmentWriter;
use App\Ark\Install\InstallStorage;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    InstallationState::resetForTests();
});

afterEach(function () {
    InstallationState::resetForTests();
});

/**
 * @return array{0: string, 1: ?string}
 */
function arkSwapEnvForKeyTest(string $contents): array
{
    $path = base_path('.env');
    $original = is_file($path) ? File::get($path) : null;
    File::put($path, $contents);

    return [$path, $original];
}

function arkRestoreEnvForKeyTest(string $path, ?string $original): void
{
    if ($original === null) {
        File::delete($path);
    } else {
        File::put($path, $original);
    }
}

function arkClearRuntimeAppKey(): void
{
    config(['app.key' => '']);
    $_ENV['APP_KEY'] = '';
    $_SERVER['APP_KEY'] = '';
    putenv('APP_KEY');
    if (app()->bound('encrypter')) {
        app()->forgetInstance('encrypter');
    }
    if (app()->bound(Illuminate\Contracts\Encryption\Encrypter::class)) {
        app()->forgetInstance(Illuminate\Contracts\Encryption\Encrypter::class);
    }
}

it('bootstraps a stable APP_KEY so /setup is reachable with an empty key', function () {
    [$path, $original] = arkSwapEnvForKeyTest("APP_NAME=ARK\nAPP_KEY=\nCUSTOM_KEEP=yes\n");
    $keyFile = InstallStorage::path('app_key');
    @unlink($keyFile);

    try {
        arkClearRuntimeAppKey();

        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Welcome to ARK', false);

        $key = (string) config('app.key');
        expect($key)->toStartWith('base64:')
            ->and(strlen($key))->toBeGreaterThan(20);

        $env = File::get($path);
        expect($env)->toContain('CUSTOM_KEEP=yes');
        expect($env)->toMatch('/^APP_KEY=.+$/m');

        arkClearRuntimeAppKey();
        $this->get(route('install.welcome'))->assertOk();
        expect((string) config('app.key'))->toBe($key);
    } finally {
        arkRestoreEnvForKeyTest($path, $original);
        @unlink($keyFile);
    }
});

it('preserves an existing valid APP_KEY byte-for-byte', function () {
    $existing = 'base64:'.base64_encode(random_bytes(32));
    [$path, $original] = arkSwapEnvForKeyTest("APP_NAME=ARK\nAPP_KEY={$existing}\n");

    try {
        config(['app.key' => $existing]);
        $_ENV['APP_KEY'] = $existing;
        $_SERVER['APP_KEY'] = $existing;
        putenv('APP_KEY='.$existing);

        app(EnsureFirstRunApplicationKey::class)->ensure();

        expect((string) config('app.key'))->toBe($existing)
            ->and(File::get($path))->toContain('APP_KEY='.$existing);
    } finally {
        arkRestoreEnvForKeyTest($path, $original);
    }
});

it('does not regenerate APP_KEY when the application is already installed', function () {
    InstallationState::markInstalled();
    [$path, $original] = arkSwapEnvForKeyTest("APP_NAME=ARK\nAPP_KEY=\n");

    try {
        arkClearRuntimeAppKey();
        $before = File::get($path);

        expect(fn () => app(EnsureFirstRunApplicationKey::class)->ensure())
            ->toThrow(RuntimeException::class, 'installed ARK instance');

        expect(File::get($path))->toBe($before)
            ->and((string) config('app.key'))->toBe('');
    } finally {
        arkRestoreEnvForKeyTest($path, $original);
    }
});

it('fails closed when APP_KEY is missing and the environment is immutable', function () {
    $path = base_path('.env');
    $original = is_file($path) ? File::get($path) : null;
    if (! is_file($path)) {
        File::put($path, "APP_KEY=\n");
    }
    $chmodOk = @chmod($path, 0444);
    $keyFile = InstallStorage::path('app_key');
    @unlink($keyFile);

    try {
        arkClearRuntimeAppKey();
        expect($chmodOk)->toBeTrue();
        expect(app(InstallerEnvironmentWriter::class)->mode())->toBe('immutable');

        expect(fn () => app(EnsureFirstRunApplicationKey::class)->ensure())
            ->toThrow(RuntimeException::class, 'not writable');
    } finally {
        @chmod($path, 0600);
        arkRestoreEnvForKeyTest($path, $original);
        @unlink($keyFile);
    }
});

it('reuses a Docker-style install app_key file without rotating', function () {
    $existing = 'base64:'.base64_encode(random_bytes(32));
    [$path, $original] = arkSwapEnvForKeyTest("APP_NAME=ARK\nAPP_KEY=\n");
    $keyFile = InstallStorage::path('app_key');
    File::ensureDirectoryExists(dirname($keyFile));
    File::put($keyFile, $existing);

    try {
        arkClearRuntimeAppKey();
        app(EnsureFirstRunApplicationKey::class)->ensure();

        expect((string) config('app.key'))->toBe($existing)
            ->and(trim(File::get($keyFile)))->toBe($existing);

        $env = File::get($path);
        expect($env)->toContain($existing);
    } finally {
        arkRestoreEnvForKeyTest($path, $original);
        @unlink($keyFile);
    }
});

it('applies a durable Docker app_key on an immutable host without writing .env', function () {
    $existing = 'base64:'.base64_encode(random_bytes(32));
    $path = base_path('.env');
    $original = is_file($path) ? File::get($path) : null;
    if (! is_file($path)) {
        File::put($path, "APP_NAME=ARK\nAPP_KEY=\n");
    }
    $chmodOk = @chmod($path, 0444);
    $keyFile = InstallStorage::path('app_key');
    File::ensureDirectoryExists(dirname($keyFile));
    File::put($keyFile, $existing);

    try {
        arkClearRuntimeAppKey();
        expect($chmodOk)->toBeTrue();
        expect(app(InstallerEnvironmentWriter::class)->mode())->toBe('immutable');

        app(EnsureFirstRunApplicationKey::class)->ensure();

        expect((string) config('app.key'))->toBe($existing)
            ->and(trim(File::get($keyFile)))->toBe($existing);

        expect(File::get($path))->not->toContain($existing);
    } finally {
        @chmod($path, 0600);
        arkRestoreEnvForKeyTest($path, $original);
        @unlink($keyFile);
    }
});
