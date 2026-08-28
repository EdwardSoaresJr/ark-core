<?php

namespace App\Ark\Growth\Integrations\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service-account OAuth for Google Growth integrations (Search Console, Business Profile, …).
 */
final class GoogleServiceAccountAccessToken
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function accessToken(array $credentials, string $scope, string $cacheKey): ?string
    {
        if ($credentials === []) {
            return null;
        }

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials, $scope): ?string {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->buildJwt($credentials, $scope),
            ]);

            if (! $response->successful()) {
                Log::warning('Google service account token exchange failed.', [
                    'scope' => $scope,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function buildJwt(array $credentials, string $scope): string
    {
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google service account credentials missing client_email or private_key.');
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = time();
        $claims = [
            'iss' => $clientEmail,
            'scope' => $scope,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $segments = "{$header}.{$payload}";

        $signature = '';
        $signed = openssl_sign($segments, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Unable to sign Google service account JWT.');
        }

        return $segments.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
