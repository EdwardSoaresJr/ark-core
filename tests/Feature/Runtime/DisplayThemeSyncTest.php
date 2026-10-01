<?php

use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config([
        'ark-ecosystem.cookie_domain' => null,
        'session.secure' => false,
    ]);
});

test('display theme toggle persists on user and sets ecosystem cookie', function () {
    $user = User::factory()->create(['display_theme' => 'system']);

    $response = $this->actingAs($user)
        ->patchJson(route('profile.display-theme.update'), [
            'display_theme' => 'dark',
        ]);

    $response->assertOk()
        ->assertJson([
            'display_theme' => 'dark',
            'dark' => true,
        ]);

    expect($user->fresh()->display_theme)->toBe('dark');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === EcosystemDisplayTheme::cookieName());

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('dark');
});

test('authenticated responses sync display theme cookie from user preference', function () {
    $user = User::factory()->create(['display_theme' => 'light']);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === EcosystemDisplayTheme::cookieName());

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('light');
});

test('oidc id token includes display and accent theme claims', function () {
    config(['oidc.enabled' => true, 'oidc.issuer' => 'http://localhost', 'oidc.shop_id' => '1']);

    app(\App\Ark\Runtime\Identity\Oidc\OidcKeyRepository::class)->createKeyPair();

    $client = \App\Ark\Runtime\Identity\Oidc\OidcClient::query()->create([
        'client_id' => 'arkademy',
        'name' => 'ARKademy',
        'client_secret' => 'spike-test-secret',
        'redirect_uris' => ['https://learn.test/oidc/callback'],
        'required_product' => 'arkademy',
        'is_confidential' => true,
    ]);

    $user = User::factory()->create([
        'display_theme' => 'dark',
        'accent_theme' => 'orange',
    ])->assignRole('advisor');

    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $query['code'],
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'client_id' => $client->client_id,
        'client_secret' => 'spike-test-secret',
        'code_verifier' => $verifier,
    ])->json();

    [, $payload] = explode('.', $token['id_token']);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);

    expect($claims['display_theme'])->toBe('dark')
        ->and($claims['accent_theme'])->toBe('orange');
});
