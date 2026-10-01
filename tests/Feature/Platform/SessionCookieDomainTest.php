<?php

use App\Ark\Runtime\Surfaces\SessionCookieDomain;
use App\Http\Middleware\ConfigureSessionCookieDomain;
use Illuminate\Http\Request;

it('uses host-only cookies on the company product host', function () {
    config([
        'surfaces.company' => 'autorepairkeeper.com',
        'surfaces.company_www' => 'www.autorepairkeeper.com',
        'surfaces.cloud_app' => 'app.autorepairkeeper.com',
    ]);

    expect(SessionCookieDomain::forHost('autorepairkeeper.com', '.demo-auto.test'))->toBeNull()
        ->and(SessionCookieDomain::forHost('www.autorepairkeeper.com', '.demo-auto.test'))->toBeNull()
        ->and(SessionCookieDomain::forHost('app.autorepairkeeper.com', '.demo-auto.test'))->toBeNull();
});

it('keeps the shared ops domain on Demo Auto Repair hosts', function () {
    config([
        'surfaces.company' => 'autorepairkeeper.com',
        'surfaces.company_www' => 'www.autorepairkeeper.com',
    ]);

    expect(SessionCookieDomain::forHost('app.demo-auto.test', '.demo-auto.test'))->toBe('.demo-auto.test')
        ->and(SessionCookieDomain::forHost('portal.demo-auto.test', '.demo-auto.test'))->toBe('.demo-auto.test')
        ->and(SessionCookieDomain::forHost('demo-auto.test', '.demo-auto.test'))->toBe('.demo-auto.test');
});

it('aligns session.domain for the request host without losing the shared source', function () {
    config([
        'session.domain' => '.demo-auto.test',
        'session.host_shared_domain' => '.demo-auto.test',
        'surfaces.company' => 'autorepairkeeper.com',
        'surfaces.company_www' => 'www.autorepairkeeper.com',
    ]);

    $middleware = new ConfigureSessionCookieDomain;

    $middleware->handle(
        Request::create('https://autorepairkeeper.com/trial', 'GET'),
        function () {
            expect(config('session.domain'))->toBeNull()
                ->and(config('session.host_shared_domain'))->toBe('.demo-auto.test');

            return response('ok');
        },
    );

    $middleware->handle(
        Request::create('https://app.demo-auto.test/app/login', 'GET'),
        function () {
            expect(config('session.domain'))->toBe('.demo-auto.test')
                ->and(config('session.host_shared_domain'))->toBe('.demo-auto.test');

            return response('ok');
        },
    );
});

it('emits host-only session cookies on the company host while ops keep Domain', function () {
    config([
        'session.domain' => '.demo-auto.test',
        'session.host_shared_domain' => '.demo-auto.test',
        'surfaces.company' => 'autorepairkeeper.com',
        'surfaces.company_www' => 'www.autorepairkeeper.com',
    ]);

    $company = $this->get('https://autorepairkeeper.com/app/login');
    $company->assertOk();

    expect(config('session.domain'))->toBeNull();

    $companySession = collect($company->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($companySession)->not->toBeNull(
        'session cookie missing; cookies='.collect($company->headers->getCookies())->map(
            fn ($c) => $c->getName().':'.var_export($c->getDomain(), true)
        )->implode(', ')
    );
    expect($companySession->getDomain())->toBeIn([null, ''], 'got domain='.var_export($companySession->getDomain(), true));

    $ops = $this->get('https://app.demo-auto.test/app/login');
    $ops->assertOk();

    expect(config('session.domain'))->toBe('.demo-auto.test');

    $opsSession = collect($ops->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($opsSession)->not->toBeNull()
        ->and($opsSession->getDomain())->toBe('.demo-auto.test');
});
