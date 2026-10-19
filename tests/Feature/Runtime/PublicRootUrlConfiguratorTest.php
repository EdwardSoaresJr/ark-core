<?php

use App\Ark\Runtime\Surfaces\PublicRootUrlConfigurator;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\URL;

/**
 * Surface-domain tests leave APP_URL in the process. Set it before boot so
 * this file checks the configurator, not whatever host leaked in.
 */
function bootPublicRootUrl(string $appUrl, array $config): void
{
    $_ENV['APP_URL'] = $appUrl;
    $_SERVER['APP_URL'] = $appUrl;
    putenv('APP_URL='.$appUrl);
    Env::getRepository()->set('APP_URL', $appUrl);

    $property = new ReflectionProperty(Env::class, 'repository');
    $property->setValue(null, null);

    test()->refreshApplication();
    config($config);
    PublicRootUrlConfigurator::apply();
}

test('signed urls use app domain when app url is localhost', function (): void {
    bootPublicRootUrl('http://localhost', [
        'app.url' => 'http://localhost',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
        'ark-ecosystem.operations_url' => 'http://localhost',
    ]);

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://app.demo-auto.test/error-reports/abc123');
});

test('a public https app url forces the https scheme', function (): void {
    bootPublicRootUrl('https://app.demo-auto.test', [
        'app.url' => 'https://app.demo-auto.test',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
    ]);

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://app.demo-auto.test/error-reports/abc123');
});

test('a non-default local app url port is kept on generated links', function (): void {
    bootPublicRootUrl('http://localhost:8088', [
        'app.url' => 'http://localhost:8088',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
        'ark-ecosystem.operations_url' => 'http://localhost',
    ]);

    expect(route('install.system'))->toBe('http://localhost:8088/setup/system');
});

test('ark operations url overrides localhost app url', function (): void {
    bootPublicRootUrl('http://localhost', [
        'app.url' => 'http://localhost',
        'surfaces.enabled' => false,
        'ark-ecosystem.operations_url' => 'https://demo.autorepairkeeper.com',
    ]);

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://demo.autorepairkeeper.com/error-reports/abc123');
});
