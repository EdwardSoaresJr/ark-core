<?php

namespace App\Ark\Runtime\Identity\Oidc;

final class OidcRsaCryptography
{
    /**
     * @return array{private: string, public: string, resource: \OpenSSLAsymmetricKey}
     */
    public static function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('Unable to generate OIDC RSA key pair.');
        }

        openssl_pkey_export($resource, $privateKey);

        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['key'])) {
            throw new \RuntimeException('Unable to export OIDC public key.');
        }

        return [
            'private' => $privateKey,
            'public' => $details['key'],
            'resource' => $resource,
        ];
    }

    public static function sign(string $payload, string $privateKeyPem, string $algorithm = 'RS256'): string
    {
        $resource = openssl_pkey_get_private($privateKeyPem);

        if ($resource === false) {
            throw new \RuntimeException('Invalid OIDC private key.');
        }

        $opensslAlgo = match ($algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            default => throw new \InvalidArgumentException("Unsupported OIDC algorithm [{$algorithm}]."),
        };

        $signature = '';
        $ok = openssl_sign($payload, $signature, $resource, $opensslAlgo);

        if (! $ok) {
            throw new \RuntimeException('OIDC JWT signing failed.');
        }

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicKeyToJwk(string $publicKeyPem, string $kid, string $algorithm = 'RS256'): array
    {
        $resource = openssl_pkey_get_public($publicKeyPem);

        if ($resource === false) {
            throw new \RuntimeException('Invalid OIDC public key.');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['rsa'])) {
            throw new \RuntimeException('OIDC public key is not RSA.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => $algorithm,
            'kid' => $kid,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];
    }
}
