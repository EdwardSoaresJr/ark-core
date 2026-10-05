<?php

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Install\InstallDraft;
use App\Ark\Install\InstallFinalizeRunner;
use App\Ark\Install\InstallMode;
use App\Ark\Install\InstallationState;
use App\Ark\Install\PendingInstallPayload;
use App\Ark\Install\SystemRequirementsChecker;
use App\Console\Commands\ArkInstallFinalizeCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    InstallationState::resetForTests();
    InstallDraft::clear();
    InstallFinalizeRunner::$fakeStart = null;
    config([
        'cache.default' => 'file',
        'install.mode' => 'self_hosted',
        'install.managed_database' => false,
    ]);
    RateLimiter::clear('install-finalize|127.0.0.1');
});

afterEach(function () {
    InstallationState::resetForTests();
    InstallDraft::clear();
    InstallFinalizeRunner::$fakeStart = null;
    config(['install.mode' => 'self_hosted', 'install.managed_database' => false]);
});

it('treats any install mode other than managed as self-hosted', function () {
    config(['install.mode' => 'managed']);
    expect(InstallMode::isManaged())->toBeTrue();

    foreach (['self_hosted', 'true', '1', 'MANAGED', '', null] as $mode) {
        config(['install.mode' => $mode]);
        expect(InstallMode::current())->toBe(InstallMode::SELF_HOSTED);
    }
});

it('does not treat a supplied database as a managed installation', function () {
    config([
        'install.mode' => 'self_hosted',
        'install.managed_database' => true,
    ]);

    $this->get('/setup')->assertOk()->assertSee('Begin Setup');
    $this->get('/setup/system')->assertOk()->assertSee('System check');
    $this->get('/setup/shop')->assertRedirect(route('install.database'));
});

it('ignores browser attempts to select managed mode', function () {
    config(['install.mode' => 'self_hosted']);

    $this->get('/setup/shop?install_mode=managed')
        ->assertRedirect(route('install.database'));

    $this->withSession(['install_mode' => 'managed'])
        ->get('/setup/shop')
        ->assertRedirect(route('install.database'));

    $this->post('/setup/shop', [
        'install_mode' => 'managed',
        'shop_name' => 'Sneaky',
        'shop_timezone' => 'America/Denver',
    ])->assertRedirect(route('install.database'));

    expect(InstallDraft::all()['shop_name'] ?? null)->toBeNull()
        ->and(InstallMode::current())->toBe(InstallMode::SELF_HOSTED);
});

it('refuses self-hosted steps before infrastructure is satisfied', function () {
    $this->get('/setup/shop')->assertRedirect(route('install.database'));
    $this->get('/setup/admin')->assertRedirect(route('install.database'));
    $this->get('/setup/review')->assertRedirect(route('install.database'));

    $this->post('/setup/admin', [
        'admin_name' => 'Admin',
        'admin_email' => 'admin@example.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('install.database'));

    expect(session('install.admin_password'))->toBeNull()
        ->and(InstallDraft::all())->not->toHaveKey('admin_email');
});

it('sends a failing self-hosted install back to the system check', function () {
    $checker = Mockery::mock(new SystemRequirementsChecker);
    $checker->shouldReceive('check')->andReturn([
        ['id' => 'php_version', 'label' => 'PHP version', 'status' => 'fail', 'detail' => 'missing'],
    ]);
    app()->instance(SystemRequirementsChecker::class, $checker);

    $this->get('/setup/database')->assertRedirect(route('install.system'));
    $this->get('/setup/shop')->assertRedirect(route('install.system'));
    $this->post('/setup/database/test', [
        'app_url' => 'https://shop.test',
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_database' => 'ark',
        'db_username' => 'ark',
        'db_password' => 'secret',
    ])->assertRedirect(route('install.system'));
});

it('opens a managed installation at the shop step', function () {
    config([
        'install.mode' => 'managed',
        'app.url' => 'https://shop.test',
        'database.connections.sqlite.host' => 'mysql',
        'database.connections.sqlite.port' => 3306,
        'database.connections.sqlite.username' => 'ark',
        'database.connections.sqlite.password' => 'from-runtime',
    ]);

    $this->get('/setup')->assertRedirect(route('install.shop'));
    $this->get('/setup/system')->assertRedirect(route('install.shop'));
    $this->get('/setup/database')->assertRedirect(route('install.shop'));
    $this->post('/setup/database/test', [
        'app_url' => 'https://evil.test',
        'db_host' => 'evil.example',
        'db_port' => 3306,
        'db_database' => 'stolen',
        'db_username' => 'root',
        'db_password' => 'nope',
    ])->assertRedirect(route('install.shop'));

    expect(InstallDraft::all()['db_host'])->toBe('mysql')
        ->and(InstallDraft::all()['db_username'])->toBe('ark')
        ->and(InstallDraft::all()['db_database'])->not->toBe('stolen')
        ->and(InstallDraft::all()['app_url'])->toBe('https://shop.test');

    $this->get('/setup/integrations')->assertNotFound();
    $this->post('/setup/integrations/connect')->assertStatus(405);
    $this->post('/setup/integrations/skip')->assertStatus(405);

    $this->get('/setup/shop')
        ->assertOk()
        ->assertSee('Shop name')
        ->assertDontSee('ARK Platform')
        ->assertDontSee(route('install.system'), false)
        ->assertDontSee(route('install.database'), false);
});

it('returns a managed installation to its current step', function () {
    config(['install.mode' => 'managed', 'app.url' => 'https://shop.test']);
    InstallDraft::merge([
        'shop_name' => 'Disposable',
        'shop_timezone' => 'America/Denver',
    ]);

    $this->get('/setup')->assertRedirect(route('install.admin'));
    $this->get('/setup/system')->assertRedirect(route('install.admin'));
    $this->get('/setup/database')->assertRedirect(route('install.admin'));
    $this->get('/setup/review')->assertRedirect(route('install.admin'));
    $this->get('/setup/shop')->assertOk();
});

it('offers Enter ARK when setup is finished', function () {
    InstallationState::markInstalled();

    $this->get('/setup/complete')
        ->assertOk()
        ->assertSee('Enter ARK')
        ->assertDontSee('ARK Platform');

    expect(route('operations.cloud.connect-after-setup', absolute: false))
        ->toBe('/app/cloud/connect-after-setup');
});

it('reaches CompleteInstallationAction from either install mode', function (string $mode) {
    config(['install.mode' => $mode, 'app.url' => 'https://shop.test']);

    if ($mode === 'managed') {
        config([
            'database.connections.sqlite.host' => 'mysql',
            'database.connections.sqlite.port' => 3306,
            'database.connections.sqlite.username' => 'ark',
            'database.connections.sqlite.password' => 'from-runtime',
        ]);
    } else {
        InstallDraft::merge([
            'app_url' => 'https://shop.test',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'ark',
            'db_username' => 'ark',
            'db_tested' => true,
            'db_managed' => false,
        ]);
    }

    InstallDraft::merge([
        'shop_name' => 'Disposable',
        'shop_timezone' => 'America/Denver',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@example.test',
        'create_workstation' => true,
    ]);

    $started = false;
    InstallFinalizeRunner::$fakeStart = function () use (&$started) {
        $started = true;
    };

    $this->withSession([
        'install.admin_password' => 'Password1!',
        'install.db_password' => 'secret',
    ])->post(route('install.run'))->assertRedirect(route('install.progress'));

    $received = PendingInstallPayload::read();
    $finalize = new ReflectionMethod(ArkInstallFinalizeCommand::class, 'handle');

    expect($started)->toBeTrue()
        ->and($finalize->getParameters()[0]->getType()->getName())->toBe(CompleteInstallationAction::class)
        ->and($received)->toMatchArray([
        'app_url' => 'https://shop.test',
        'skip_integrations' => true,
        'create_workstation' => true,
    ])
        ->and($received['shop']['shop_name'])->toBe('Disposable')
        ->and($received['admin']['email'])->toBe('admin@example.test')
        ->and($received['admin']['password'])->toBe('Password1!')
        ->and($received['db']['host'])->toBe($mode === 'managed' ? 'mysql' : '127.0.0.1')
        ->and($received['db']['password'])->toBe($mode === 'managed' ? 'from-runtime' : 'secret');
})->with(['self_hosted', 'managed']);

it('leaves no installer platform step in the setup views', function () {
    expect(File::exists(resource_path('views/install/integrations.blade.php')))->toBeFalse();
    expect(file_get_contents(resource_path('views/install/complete.blade.php')))
        ->not->toContain('ARK Platform')
        ->not->toContain('connect-after-setup');
});
