<?php

use App\Ark\Install\InstallationState;

beforeEach(function () {
    InstallationState::resetForTests();
});

afterEach(function () {
    InstallationState::resetForTests();
});

it('reports not_installed and exits 1 for --check-installed on a fresh host', function () {
    $this->artisan('ark:install-status')
        ->expectsOutputToContain('status=not_installed')
        ->expectsOutputToContain('installed=no')
        ->assertSuccessful();

    $this->artisan('ark:install-status', ['--check-installed' => true, '--quiet' => true])
        ->assertExitCode(1);
});

it('reports installed and exits 0 for --check-installed after markInstalled', function () {
    InstallationState::markInstalled();

    $this->artisan('ark:install-status', ['--quiet' => true, '--check-installed' => true])
        ->assertExitCode(0);

    $this->artisan('ark:install-status')
        ->expectsOutputToContain('status=installed')
        ->expectsOutputToContain('installed=yes')
        ->assertSuccessful();
});

it('does not treat APP_KEY presence as installed', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    putenv('APP_KEY='.config('app.key'));
    $_ENV['APP_KEY'] = config('app.key');
    $_SERVER['APP_KEY'] = config('app.key');

    expect(InstallationState::isInstalled())->toBeFalse();

    $this->artisan('ark:install-status', ['--check-installed' => true, '--quiet' => true])
        ->assertExitCode(1);
});

it('reads installation state without requiring application schema tables', function () {
    expect(InstallationState::path())->toContain(DIRECTORY_SEPARATOR.'install'.DIRECTORY_SEPARATOR)
        ->and(InstallationState::isInstalled())->toBeFalse();

    InstallationState::markInstalled();

    expect(is_file(InstallationState::path()))->toBeTrue()
        ->and(InstallationState::isInstalled())->toBeTrue();
});
