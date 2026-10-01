<?php

namespace App\Ark\Runtime\Identity\Oidc;

final class OidcJwtDecoder
{
    public function __construct(
        private readonly OidcKeyRepository $keys,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function decodeAndVerify(string $jwt): array
    {
        $segments = explode('.', $jwt);

        if (count($segments) !== 3) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'Malformed JWT.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);
        $kid = (string) ($header['kid'] ?? '');

        $signingKey = OidcSigningKey::query()->where('kid', $kid)->first();

        if ($signingKey === null || $signingKey->isRevoked()) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'Signing key is not available.');
        }

        $signingInput = $encodedHeader.'.'.$encodedPayload;
        $signature = $this->base64UrlDecode($encodedSignature);
        $publicKey = openssl_pkey_get_public($this->keys->publicKeyPem($signingKey));

        if ($publicKey === false) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'Invalid signing key.');
        }

        $valid = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($valid !== 1) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'JWT signature verification failed.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'JWT has expired.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new OidcAuthorizationDeniedException('invalid_token', 'Invalid base64 in JWT.');
        }

        return $decoded;
    }
}
