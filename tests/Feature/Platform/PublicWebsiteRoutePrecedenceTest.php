<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\Http\PublicWebsiteController;
use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Env;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @return array<string, array{env: ?string, server: ?string, putenv: string|false}>
 */
function useNativeWebsiteHost(): array
{
    $vars = [
        'SURFACE_DOMAINS_ENABLED' => 'true',
        'APP_DOMAIN' => 'lugsnplugs.arksms.com',
        'PORTAL_DOMAIN' => 'portal.lugsnplugs.com',
        'PUBLIC_DOMAIN' => 'lugsnplugs.com',
        'SURFACE_PUBLIC_ALIASES' => 'lugsnplugs.arksms.com',
        'APP_URL' => 'https://lugsnplugs.arksms.com',
        'ARK_WEBSITE_HOST' => 'lugsnplugs.arksms.com',
        'WEBSITE_CUSTOM_DOMAIN' => 'lugsnplugs.com',
        'WEBSITE_CANONICAL_USES_CUSTOM_DOMAIN' => 'true',
    ];

    $prior = [];
    foreach ($vars as $key => $value) {
        $prior[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'putenv' => getenv($key),
        ];
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
        Env::getRepository()->set($key, $value);
    }

    return $prior;
}

/**
 * @param  array<string, array{env: ?string, server: ?string, putenv: string|false}>  $prior
 */
function restoreWebsiteHostEnv(array $prior): void
{
    foreach ($prior as $key => $snapshot) {
        if ($snapshot['env'] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $snapshot['env'];
        }

        if ($snapshot['server'] === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $snapshot['server'];
        }

        if ($snapshot['putenv'] === false) {
            putenv($key);
        } else {
            putenv($key.'='.$snapshot['putenv']);
        }

        $restored = $_ENV[$key] ?? $_SERVER[$key] ?? false;
        if (is_string($restored)) {
            Env::getRepository()->set($key, $restored);
        }
    }
}

function websiteFallbackProbeUri(Route $route): ?string
{
    $uri = $route->uri();
    if (! preg_match_all('/\{(\w+)\??\}/', $uri, $names)) {
        return $uri;
    }

    foreach ($names[1] as $name) {
        $sample = websiteFallbackProbeValue($route->wheres[$name] ?? null);
        if ($sample === null) {
            return null;
        }

        $uri = preg_replace('/\{'.$name.'\??\}/', $sample, $uri, 1);
    }

    return $uri;
}

function websiteFallbackProbeValue(?string $where): ?string
{
    if ($where === null || $where === '.*' || $where === '.+') {
        return 'sample';
    }

    if (! preg_match('/^\[([^\]]+)\]/', $where, $class)) {
        return null;
    }

    $token = str_contains($class[1], 'a-z') || str_contains($class[1], 'A-Z')
        ? 'a'
        : (str_contains($class[1], '0-9') ? '1' : null);
    if ($token === null) {
        return null;
    }

    if (preg_match('/\{(\d+)\}/', $where, $length)) {
        return str_repeat($token, (int) $length[1]);
    }

    return $token;
}

function publishNativeWebsiteForPrecedence(): void
{
    ShopSettings::current()->update([
        'shop_name' => 'LugsNPlugs Automotive',
        'phone' => '7194136227',
        'email' => 'hello@lugsnplugs.com',
        'address_line_1' => '3445 Chelton Loop N',
        'address_line_2' => 'D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
    ]);
    ShopSettings::forgetCurrent();

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', PublicWebsiteCatalog::document(), true);
}
function resetEnvRepository(): void
{
    $property = new ReflectionProperty(Env::class, 'repository');
    $property->setValue(null, null);
}

beforeEach(function (): void {
    $this->priorWebsiteHostEnv = useNativeWebsiteHost();
    resetEnvRepository();
    $this->refreshApplication();
    $this->withoutVite();
});

afterEach(function (): void {
    restoreWebsiteHostEnv($this->priorWebsiteHostEnv);
});

test('explicit core routes win over the website fallback on the native host', function (): void {
    $native = 'lugsnplugs.arksms.com';
    $custom = 'lugsnplugs.com';
    $shadowed = [];
    $checked = [];

    foreach (app('router')->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || $route->isFallback) {
            continue;
        }

        $name = (string) $route->getName();
        if (str_starts_with($name, 'public.')) {
            continue;
        }

        $domain = $route->getDomain();
        if ($domain !== null && ! in_array($domain, [$native, $custom], true)) {
            continue;
        }

        $uri = websiteFallbackProbeUri($route);
        if ($uri === null) {
            continue;
        }

        $host = $domain ?? $native;
        $request = Request::create('http://'.$host.'/'.ltrim($uri, '/'), 'GET');
        if (! $route->matches($request)) {
            continue;
        }

        try {
            $matched = app('router')->getRoutes()->match($request);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            $shadowed[] = $host.'/'.$uri;

            continue;
        }

        $checked[] = $matched->getName() ?: $matched->uri();
        $action = $matched->getActionName();
        if ($matched->isFallback || $action === PublicWebsiteController::class.'@legacy') {
            $shadowed[] = ($matched->getName() ?: $matched->uri()).' <= '.$host.'/'.$uri;
        }
    }

    expect($shadowed)->toBe([])
        ->and($checked)->toContain('login', 'health.reverb', 'install.system', 'webhooks.cloud.website.show', 'api.desk.me');

    $protected = [
        'http://'.$native.'/up' => null,
        'http://'.$native.'/up/reverb' => 'health.reverb',
        'http://'.$native.'/app/login' => 'login',
        'http://'.$native.'/setup/system' => 'install.system',
        'http://'.$native.'/webhooks/cloud/website/lugsnplugs.arksms.com' => 'webhooks.cloud.website.show',
        'http://'.$native.'/api/desk/me' => 'api.desk.me',
        'http://'.$native.'/.well-known/openid-configuration' => 'oidc.discovery',
    ];

    foreach ($protected as $url => $expectedName) {
        $matched = app('router')->getRoutes()->match(Request::create($url, 'GET'));

        expect($matched->isFallback)->toBeFalse()
            ->and($matched->getActionName())->not->toBe(PublicWebsiteController::class.'@legacy');

        if ($expectedName !== null) {
            expect($matched->getName())->toBe($expectedName);
        }
    }

    $domains = collect(app('router')->getRoutes())->map(fn (Route $route): ?string => $route->getDomain())->unique()->all();
    expect($domains)->not->toContain('www.lugsnplugs.com');
});

test('native host keeps system routes and renders the website', function (): void {
    $this->artisan('migrate', ['--force' => true]);
    publishNativeWebsiteForPrecedence();

    $this->get('http://lugsnplugs.arksms.com/up')
        ->assertOk()
        ->assertHeaderMissing('X-ARK-Website')
        ->assertSee('Application up');

    $this->get('http://lugsnplugs.arksms.com/up/reverb')
        ->assertOk()
        ->assertHeaderMissing('X-ARK-Website')
        ->assertJsonStructure(['reverb_host']);

    $this->get('http://lugsnplugs.arksms.com/app/login')
        ->assertOk()
        ->assertHeaderMissing('X-ARK-Website')
        ->assertSee('Sign in');

    $this->get('http://lugsnplugs.arksms.com/setup/system')
        ->assertHeaderMissing('X-ARK-Website')
        ->assertDontSee('This website is not published.');

    $this->get('http://lugsnplugs.arksms.com/webhooks/cloud/website/lugsnplugs.arksms.com')
        ->assertUnauthorized()
        ->assertHeaderMissing('X-ARK-Website');

    $this->get('http://lugsnplugs.arksms.com/')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core')
        ->assertSee('Accurate Diagnostics. Honest Repairs.');

    $this->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.arksms.com/common-problems/p0420')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.arksms.com/llms.txt')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.arksms.com/appointment')
        ->assertStatus(301)
        ->assertHeader('X-ARK-Website', 'core')
        ->assertHeader('Location', 'https://lugsnplugs.arksms.com/book');

    $this->get('http://lugsnplugs.arksms.com/about')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core')
        ->assertSee('A repair shop built around doing it right')
        ->assertDontSee('This website is not published.');
});

test('custom domain renders the website and does not serve staff routes', function (): void {
    $this->artisan('migrate', ['--force' => true]);
    publishNativeWebsiteForPrecedence();

    $this->get('http://lugsnplugs.com/')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core')
        ->assertSee('Accurate Diagnostics. Honest Repairs.');

    $this->get('http://lugsnplugs.com/book')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.com/common-problems/p0420')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.com/llms.txt')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core');

    $this->get('http://lugsnplugs.com/appointment?concern=Brakes')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://lugsnplugs.com/book?concern=Brakes');

    $this->get('http://lugsnplugs.com/about')
        ->assertOk()
        ->assertHeader('X-ARK-Website', 'core')
        ->assertSee('A repair shop built around doing it right');

    $this->get('http://lugsnplugs.com/app/login')
        ->assertRedirect('https://lugsnplugs.arksms.com/app/login')
        ->assertHeaderMissing('X-ARK-Website');

    $this->get('http://lugsnplugs.com/webhooks/cloud/website/lugsnplugs.arksms.com')
        ->assertRedirect('https://lugsnplugs.arksms.com/webhooks/cloud/website/lugsnplugs.arksms.com')
        ->assertHeaderMissing('X-ARK-Website');

    $this->get('http://www.lugsnplugs.com/')
        ->assertStatus(301)
        ->assertRedirect('https://lugsnplugs.com/')
        ->assertHeaderMissing('X-ARK-Website');

    $this->get('http://evil.example/book')
        ->assertNotFound()
        ->assertHeaderMissing('X-ARK-Website');
});
