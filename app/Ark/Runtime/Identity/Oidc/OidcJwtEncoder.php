<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Models\User;

final class OidcJwtEncoder
{
    public function __construct(
        private readonly OidcKeyRepository $keys,
        private readonly OidcClaimsBuilder $claims,
    ) {}

    /**
     * @param  array<string, mixed>  $extraClaims
     */
    public function idToken(User $user, string $clientId, array $extraClaims = []): string
    {
        $signingKey = $this->keys->activeSigningKey();
        $now = time();
        $ttl = (int) config('oidc.id_token_ttl_seconds');

        $payload = array_merge($this->claims->forUser($user, $clientId), $extraClaims, [
            'iss' => config('oidc.issuer'),
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + $ttl,
            'auth_time' => $now,
        ]);

        return $this->encode($payload, $signingKey);
    }

    public function accessToken(User $user, string $clientId): string
    {
        $signingKey = $this->keys->activeSigningKey();
        $now = time();
        $ttl = (int) config('oidc.access_token_ttl_seconds');

        $payload = array_merge($this->claims->forUser($user, $clientId), [
            'iss' => config('oidc.issuer'),
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + $ttl,
            'token_use' => 'access',
        ]);

        return $this->encode($payload, $signingKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload, OidcSigningKey $signingKey): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $signingKey->algorithm,
            'kid' => $signingKey->kid,
        ];

        $segments = [
            $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);
        $signature = OidcRsaCryptography::sign(
            $signingInput,
            $this->keys->privateKeyPem($signingKey),
            $signingKey->algorithm,
        );

        return $signingInput.'.'.$signature;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
