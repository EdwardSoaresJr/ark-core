<?php

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\HostedRuntime;
use App\Http\Middleware\ApplyShopRuntimeConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeStanclTenancy;

beforeEach(function () {
    $this->catalog = tempnam(sys_get_temp_dir(), 'ark-tenants-');
    $this->originalDefault = config('database.default');
    $this->originalConnection = config('database.connections.'.$this->originalDefault);
    $this->originalReplyTo = config('mail.reply_to.address');
    FakeStanclTenancy::clear();
});

afterEach(function () {
    putenv('ARK_HOSTED_TENANTS');
    config([
        'database.default' => $this->originalDefault,
        'database.connections.'.$this->originalDefault => $this->originalConnection,
        'mail.reply_to.address' => $this->originalReplyTo,
    ]);
    ShopSettings::forgetCurrent();
    ShopDisplayTimezone::forget();
    InstallationState::markInstalled();
    FakeStanclTenancy::clear();
    if (is_string($this->catalog) && is_file($this->catalog)) {
        unlink($this->catalog);
    }
});

it('keeps redis drivers when a hosted catalog exists before the shop is installed', function () {
    InstallationState::resetForTests();
    putenv('ARK_HOSTED_TENANTS='.$this->catalog);
    config([
        'session.driver' => 'redis',
        'cache.default' => 'shop',
        'queue.default' => 'redis',
    ]);

    expect(HostedRuntime::shouldUseFileDriversBeforeInstall())->toBeFalse()
        ->and(config('session.driver'))->toBe('redis')
        ->and(config('cache.default'))->toBe('shop')
        ->and(config('queue.default'))->toBe('redis');
});

it('still forces file drivers for a self-hosted shop that is not installed', function () {
    InstallationState::resetForTests();
    putenv('ARK_HOSTED_TENANTS');

    expect(HostedRuntime::catalogConfigured())->toBeFalse()
        ->and(HostedRuntime::isHostedProduct())->toBeFalse()
        ->and(HostedRuntime::shouldUseFileDriversBeforeInstall())->toBeTrue();
});

it('treats an initialized Stancl tenant as a hosted product without ARK_HOSTED_TENANTS', function () {
    putenv('ARK_HOSTED_TENANTS');
    FakeStanclTenancy::enable();

    expect(HostedRuntime::catalogConfigured())->toBeFalse()
        ->and(HostedRuntime::stanclTenantInitialized())->toBeTrue()
        ->and(HostedRuntime::isHostedProduct())->toBeTrue()
        ->and(HostedRuntime::tenantDatabaseSelected())->toBeTrue();
});

it('does not open the image default database before the hosted tenant is selected', function () {
    putenv('ARK_HOSTED_TENANTS='.$this->catalog);
    config([
        'database.connections.'.$this->originalDefault.'.host' => '127.0.0.1',
        'database.connections.'.$this->originalDefault.'.database' => 'laravel',
        'mail.reply_to.address' => 'before@example.test',
    ]);
    ShopSettings::forgetCurrent();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(HostedRuntime::tenantDatabaseSelected())->toBeFalse();

    $settings = ShopSettings::current();
    ShopIntegrationRuntimeConfig::apply();
    ShopDisplayTimezone::apply();

    expect($settings->exists)->toBeFalse()
        ->and($queries)->toBe(0)
        ->and(config('mail.reply_to.address'))->toBe('before@example.test');
});

it('reads shop settings once the hosted tenant connection is selected', function () {
    putenv('ARK_HOSTED_TENANTS='.$this->catalog);
    ShopSettings::forgetCurrent();

    expect(HostedRuntime::tenantDatabaseSelected())->toBeTrue();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    ShopIntegrationRuntimeConfig::apply();
    ShopDisplayTimezone::apply();

    expect($queries)->toBeGreaterThan(0);
});

it('applies shop runtime config from the middleware only after the tenant database is selected', function () {
    putenv('ARK_HOSTED_TENANTS='.$this->catalog);
    config([
        'database.connections.'.$this->originalDefault.'.host' => '127.0.0.1',
        'database.connections.'.$this->originalDefault.'.database' => 'laravel',
        'mail.reply_to.address' => 'before@example.test',
    ]);

    $response = (new ApplyShopRuntimeConfig)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(config('mail.reply_to.address'))->toBe('before@example.test');
});
