<?php

namespace App\Ark\Mobile\Push\Transport\Firebase;

use App\Ark\Mobile\Push\MobilePushSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Firebase HTTP v1 credentials — implementation detail of {@see FirebasePushTransport}. */
final class FcmAccessTokenProvider
{
    private const CACHE_KEY = 'mobile:push-transport:firebase:access_token';

    public function token(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function (): ?string {
            $credentials = $this->credentials();

            if ($credentials === null) {
                return null;
            }

            $jwt = $this->buildJwt($credentials);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('Push transport credential exchange failed.', [
                    'transport' => 'firebase',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    public function isConfigured(): bool
    {
        return MobilePushSettings::current()->isOperational();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        return MobilePushSettings::current()->credentialsArray();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function buildJwt(array $credentials): string
    {
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Push transport credentials missing client_email or private_key.');
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = time();
        $claims = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $segments = "{$header}.{$payload}";

        $signature = '';
        $signed = openssl_sign($segments, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Unable to sign push transport JWT.');
        }

        return $segments.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
