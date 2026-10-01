<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Models\User;
use Illuminate\Support\Str;

final class OidcAuthorizationService
{
    public function __construct(
        private readonly OidcProductAccessResolver $productAccess,
    ) {}

    public function assertUserMayAuthorize(User $user, OidcClient $client): void
    {
        if (! $user->isActive()) {
            throw new OidcAuthorizationDeniedException('inactive_user', 'User account is inactive.');
        }

        if (! $this->productAccess->canAccessProduct($user, $client->required_product)) {
            throw new OidcAuthorizationDeniedException('access_denied', 'User lacks required product access.');
        }
    }

    public function createAuthorizationCode(
        User $user,
        OidcClient $client,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
        array $scopes,
    ): OidcAuthorizationCode {
        return OidcAuthorizationCode::query()->create([
            'code' => Str::random(64),
            'user_id' => $user->id,
            'oidc_client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'scopes' => $scopes,
            'expires_at' => now()->addSeconds((int) config('oidc.authorization_code_ttl_seconds')),
        ]);
    }

    public function exchangeAuthorizationCode(
        string $code,
        OidcClient $client,
        string $redirectUri,
        string $codeVerifier,
    ): OidcAuthorizationCode {
        $authorizationCode = OidcAuthorizationCode::query()
            ->where('code', $code)
            ->where('oidc_client_id', $client->id)
            ->first();

        if ($authorizationCode === null) {
            throw new OidcAuthorizationDeniedException('invalid_grant', 'Authorization code is invalid.');
        }

        if ($authorizationCode->isConsumed() || $authorizationCode->isExpired()) {
            throw new OidcAuthorizationDeniedException('invalid_grant', 'Authorization code is expired or used.');
        }

        if (! hash_equals($authorizationCode->redirect_uri, $redirectUri)) {
            throw new OidcAuthorizationDeniedException('invalid_grant', 'Redirect URI mismatch.');
        }

        if (! $authorizationCode->matchesPkce($codeVerifier)) {
            throw new OidcAuthorizationDeniedException('invalid_grant', 'PKCE verification failed.');
        }

        $user = $authorizationCode->user()->firstOrFail();
        $this->assertUserMayAuthorize($user, $client);

        $authorizationCode->update(['consumed_at' => now()]);

        return $authorizationCode->fresh(['user']);
    }
}
