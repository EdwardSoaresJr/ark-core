<?php

use App\Ark\Runtime\Surfaces\PublicRootUrlConfigurator;
use Illuminate\Support\Facades\URL;

test('signed urls use app domain when app url is localhost', function (): void {
    config([
        'app.url' => 'http://localhost',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
        'ark-ecosystem.operations_url' => 'http://localhost',
    ]);

    PublicRootUrlConfigurator::apply();

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://app.demo-auto.test/error-reports/abc123');
});

test('configured app url is used when not localhost', function (): void {
    config([
        'app.url' => 'https://app.demo-auto.test',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
    ]);

    PublicRootUrlConfigurator::apply();

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://app.demo-auto.test/error-reports/abc123');
});

test('ark operations url overrides localhost app url', function (): void {
    config([
        'app.url' => 'http://localhost',
        'surfaces.enabled' => false,
        'ark-ecosystem.operations_url' => 'https://demo.autorepairkeeper.com',
    ]);

    PublicRootUrlConfigurator::apply();

    $url = URL::temporarySignedRoute(
        'runtime.exception-reports.copy',
        now()->addDay(),
        ['reportId' => 'abc123'],
    );

    expect($url)->toStartWith('https://demo.autorepairkeeper.com/error-reports/abc123');
});
