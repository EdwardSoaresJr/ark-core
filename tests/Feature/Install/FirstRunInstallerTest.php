<?php

use App\Ark\Install\InstallDraft;
use App\Ark\Install\InstallFinalizeRunner;
use App\Ark\Install\InstallationState;
use App\Ark\Install\InstallerEnvironmentWriter;
use App\Ark\Install\PendingInstallPayload;
use App\Ark\Install\SystemRequirementsChecker;
use Illuminate\Support\Facades\File;


beforeEach(function () {
    InstallationState::resetForTests();
    InstallFinalizeRunner::$fakeStart = null;
});

afterEach(function () {
    InstallFinalizeRunner::$fakeStart = null;
    InstallationState::resetForTests();
});

it('reports not installed on a fresh tree and redirects the app to setup', function () {
    expect(InstallationState::isInstalled())->toBeFalse();

    $this->get('/')->assertRedirect(route('install.welcome'));
    $this->get('/app/login')->assertRedirect(route('install.welcome'));
});

it('serves the setup wizard without authentication', function () {
    $this->get(route('install.welcome'))->assertOk()->assertSee('Welcome to ARK');
    $this->get(route('install.system'))->assertOk()->assertSee('System check');
});

it('locks setup mutations after installation', function () {
    InstallationState::markInstalled();

    $this->get(route('install.welcome'))->assertRedirect(route('login'));
    $this->post(route('install.database.test'), [])->assertForbidden();
});

it('rejects arbitrary environment keys in the writer', function () {
    $writer = app(InstallerEnvironmentWriter::class);

    expect(fn () => $writer->write(['EVIL_KEY' => 'x']))
        ->toThrow(\InvalidArgumentException::class);
});

it('preserves unrelated env lines when writing allowlisted keys', function () {
    $path = base_path('.env');
    $original = is_file($path) ? File::get($path) : null;
    File::put($path, "APP_NAME=ARK\nCUSTOM_KEEP=yes\nDB_HOST=old\n");

    try {
        app(InstallerEnvironmentWriter::class)->write([
            'DB_HOST' => '127.0.0.1',
            'DB_DATABASE' => 'ark_test',
        ]);
        $contents = File::get($path);
        expect($contents)->toContain('CUSTOM_KEEP=yes')
            ->and($contents)->toContain('DB_HOST=127.0.0.1')
            ->and($contents)->toContain('DB_DATABASE=ark_test')
            ->and($contents)->not->toContain('EVIL');
    } finally {
        if ($original === null) {
            File::delete($path);
        } else {
            File::put($path, $original);
        }
    }
});

it('system check fails closed on missing hard requirements list shape', function () {
    $checks = app(SystemRequirementsChecker::class)->check();
    expect($checks)->not->toBeEmpty();
    foreach ($checks as $check) {
        expect($check)->toHaveKeys(['id', 'label', 'status', 'detail']);
        expect($check['status'])->toBeIn(['pass', 'warning', 'fail']);
    }
});

it('exposes install status via artisan without secrets', function () {
    $this->artisan('ark:install-status')
        ->expectsOutputToContain('status=not_installed')
        ->assertSuccessful();
});

it('recover refuses to unlock an installed shop', function () {
    InstallationState::markInstalled();
    $this->artisan('ark:install-recover', ['--force' => true])
        ->assertFailed();
    expect(InstallationState::isInstalled())->toBeTrue();
});

it('install POST returns to progress without waiting for migrate', function () {
    InstallDraft::merge([
        'app_url' => 'https://example.test',
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_database' => 'ark',
        'db_username' => 'ark',
        'db_tested' => true,
        'shop_name' => 'Demo Shop',
        'shop_timezone' => 'America/Denver',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@example.test',
        'create_workstation' => true,
    ]);

    $started = false;
    InstallFinalizeRunner::$fakeStart = function () use (&$started): void {
        $started = true;
        expect(PendingInstallPayload::read())->not->toBeNull();
        InstallationState::markInProgress('migrate');
    };

    $this->withSession([
        'install.admin_password' => 'Password1!',
        'install.db_password' => 'secret',
    ])->post(route('install.run'))
        ->assertRedirect(route('install.progress'));

    expect($started)->toBeTrue()
        ->and(InstallationState::isActivelyInstalling())->toBeTrue()
        ->and(session('install.admin_password'))->toBeNull();

    $this->get(route('install.progress'))
        ->assertOk()
        ->assertSee('Installing ARK');

    $this->getJson(route('install.progress.status'))
        ->assertOk()
        ->assertJsonPath('phase', 'running')
        ->assertJsonPath('checkpoint', 'migrate');

    InstallationState::markInstalled();
    PendingInstallPayload::clear();

    $this->getJson(route('install.progress.status'))
        ->assertOk()
        ->assertJsonPath('phase', 'complete');
});

it('progress status reports terminal install failure', function () {
    InstallationState::markFailed('exception', 'Boom');

    $this->getJson(route('install.progress.status'))
        ->assertOk()
        ->assertJsonPath('phase', 'failed')
        ->assertJsonPath('message', 'Boom');
});
