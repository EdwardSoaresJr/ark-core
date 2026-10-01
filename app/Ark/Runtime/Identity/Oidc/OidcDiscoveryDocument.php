<?php

namespace App\Ark\Runtime\Identity\Oidc;

final class OidcDiscoveryDocument
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $issuer = config('oidc.issuer');

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'email', 'profile', 'groups'],
            'claims_supported' => OidcClaimsBuilder::allowedClaimNames(),
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }
}
