<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Identity\Oidc\OidcClaimsBuilder;
use App\Ark\Runtime\Identity\Oidc\OidcClient;
use App\Ark\Runtime\Identity\Oidc\OidcJwtDecoder;
use App\Ark\Runtime\Identity\Oidc\OidcKeyRepository;
use App\Ark\Runtime\Identity\Oidc\OidcProduct;
use App\Ark\Runtime\Identity\Oidc\UserProductAccess;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('oidc.enabled', true);
    Config::set('oidc.issuer', 'http://localhost');
    Config::set('oidc.shop_id', '1');

    $this->seed(ArkAuthorizationSeeder::class);
    app(OidcKeyRepository::class)->createKeyPair();
});

function oidcPkcePair(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function seedOidcArkademyClient(string $secret = 'spike-test-secret'): OidcClient
{
    return OidcClient::query()->create([
        'client_id' => 'arkademy',
        'name' => 'ARKademy',
        'client_secret' => $secret,
        'redirect_uris' => ['https://learn.test/oidc/callback'],
        'required_product' => OidcProduct::Arkademy->value,
        'is_confidential' => true,
    ]);
}

function decodeJwtPayload(string $jwt): array
{
    [, $payload] = explode('.', $jwt);

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
}

test('oidc discovery and jwks endpoints are available when enabled', function () {
    $discovery = $this->get('/.well-known/openid-configuration')->json();
    $jwks = $this->get('/.well-known/jwks.json')->json();

    expect($discovery['issuer'])->toBe('http://localhost')
        ->and($discovery['authorization_endpoint'])->toBe('http://localhost/oauth/authorize')
        ->and($discovery['token_endpoint'])->toBe('http://localhost/oauth/token')
        ->and($discovery['jwks_uri'])->toBe('http://localhost/.well-known/jwks.json')
        ->and($jwks['keys'])->toHaveCount(1);
});

test('oidc endpoints are hidden when issuer is disabled', function () {
    Config::set('oidc.enabled', false);

    $this->get('/.well-known/openid-configuration')->assertNotFound();
});

test('unauthenticated oidc authorize redirects to staff login', function () {
    seedOidcArkademyClient();
    [, $challenge] = oidcPkcePair();

    $response = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => 'arkademy',
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect(route('login'));
});

test('staff oidc flow issues stable sub and closed claims', function () {
    $client = seedOidcArkademyClient();
    [$verifier, $challenge] = oidcPkcePair();
    $user = User::factory()->create(['email' => 'advisor@demo-auto.test'])->assignRole(ArkRole::Advisor->value);

    $authorize = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid email profile groups',
        'state' => 'bookstack-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $authorize->assertRedirect();
    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('code');

    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $query['code'],
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'client_id' => $client->client_id,
        'client_secret' => 'spike-test-secret',
        'code_verifier' => $verifier,
    ]);

    $tokenResponse->assertOk();
    $body = $tokenResponse->json();

    $idPayload = decodeJwtPayload($body['id_token']);

    expect($idPayload['sub'])->toBe((string) $user->id)
        ->and($idPayload['email'])->toBe('advisor@demo-auto.test')
        ->and($idPayload['groups'])->toContain('advisor')
        ->and($idPayload['products'])->toContain('arkademy')
        ->and($idPayload['shop_id'])->toBe('1');

    $allowedKeys = array_merge(
        ['iss', 'aud', 'iat', 'exp', 'auth_time'],
        OidcClaimsBuilder::allowedClaimNames(),
    );
    expect(array_diff(array_keys($idPayload), $allowedKeys))->toBeEmpty();

    $user->update(['email' => 'renamed@demo-auto.test']);
    $user->refresh();

    [$verifier2, $challenge2] = oidcPkcePair();
    $authorize2 = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge2,
        'code_challenge_method' => 'S256',
    ]));
    parse_str(parse_url($authorize2->headers->get('Location'), PHP_URL_QUERY), $query2);

    $token2 = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $query2['code'],
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'client_id' => $client->client_id,
        'client_secret' => 'spike-test-secret',
        'code_verifier' => $verifier2,
    ])->json();

    expect(decodeJwtPayload($token2['id_token'])['sub'])->toBe((string) $user->id)
        ->and(decodeJwtPayload($token2['id_token'])['email'])->toBe('renamed@demo-auto.test');
});

test('product access gate denies users without arkademy before token issuance', function () {
    $client = seedOidcArkademyClient();
    [$verifier, $challenge] = oidcPkcePair();
    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);

    UserProductAccess::query()->create([
        'user_id' => $user->id,
        'product_slug' => OidcProduct::Arkademy->value,
        'granted' => false,
    ]);

    UserProductAccess::query()->create([
        'user_id' => $user->id,
        'product_slug' => OidcProduct::ArkSms->value,
        'granted' => true,
    ]);

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('error=access_denied');
});

test('legacy ark_v2 product slug normalizes to ark_sms for access checks', function () {
    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);

    UserProductAccess::query()->create([
        'user_id' => $user->id,
        'product_slug' => 'ark_v2',
        'granted' => true,
    ]);

    UserProductAccess::query()->create([
        'user_id' => $user->id,
        'product_slug' => OidcProduct::Arkademy->value,
        'granted' => false,
    ]);

    $resolver = app(\App\Ark\Runtime\Identity\Oidc\OidcProductAccessResolver::class);

    expect($resolver->canAccessProduct($user, OidcProduct::ArkSms->value))->toBeTrue()
        ->and($resolver->canAccessProduct($user, 'ark_v2'))->toBeTrue()
        ->and($resolver->resolve($user))->toContain('ark_sms')
        ->and($resolver->resolve($user))->not->toContain('ark_v2');
});

test('inactive users are denied at authorize', function () {
    $client = seedOidcArkademyClient();
    [, $challenge] = oidcPkcePair();
    $user = User::factory()->inactive()->create()->assignRole(ArkRole::Advisor->value);

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    expect($response->headers->get('Location'))->toContain('error=inactive_user');
});

test('customer role cannot authorize staff oidc client', function () {
    $client = seedOidcArkademyClient();
    [, $challenge] = oidcPkcePair();
    $user = User::factory()->create()->assignRole(ArkRole::Customer->value);

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    expect($response->headers->get('Location'))->toContain('error=access_denied');
});

test('jwks rotation publishes overlap keys and decoder accepts active kid', function () {
    $keys = app(OidcKeyRepository::class);
    $firstKid = $keys->activeSigningKey()->kid;
    $keys->rotateKeyPair();

    $jwks = $this->get('/.well-known/jwks.json')->json('keys');
    expect(collect($jwks)->pluck('kid')->all())->toContain($firstKid);

    $client = seedOidcArkademyClient();
    [$verifier, $challenge] = oidcPkcePair();
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

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

    $decoder = app(OidcJwtDecoder::class);
    $payload = $decoder->decodeAndVerify($token['access_token']);

    expect($payload['sub'])->toBe((string) $user->id);

    $keys->revokeKey($firstKid);
    $published = collect($this->get('/.well-known/jwks.json')->json('keys'))->pluck('kid')->all();
    expect($published)->not->toContain($firstKid);
});

test('token endpoint accepts client secret via http basic auth', function () {
    $client = seedOidcArkademyClient();
    [$verifier, $challenge] = oidcPkcePair();
    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $authorize = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));
    parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $basic = base64_encode($client->client_id.':spike-test-secret');

    $token = $this->withHeader('Authorization', 'Basic '.$basic)->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $query['code'],
        'redirect_uri' => 'https://learn.test/oidc/callback',
        'client_id' => $client->client_id,
        'code_verifier' => $verifier,
    ])->json();

    expect($token)->toHaveKey('id_token')
        ->and(decodeJwtPayload($token['id_token'])['sub'])->toBe((string) $user->id);
});

test('userinfo returns closed claims for bearer access token', function () {
    $client = seedOidcArkademyClient();
    [$verifier, $challenge] = oidcPkcePair();
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

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

    $userinfo = $this->withHeader('Authorization', 'Bearer '.$token['access_token'])
        ->get('/oauth/userinfo')
        ->json();

    expect(array_diff(array_keys($userinfo), OidcClaimsBuilder::allowedClaimNames()))->toBeEmpty()
        ->and($userinfo['sub'])->toBe((string) $user->id);
});
