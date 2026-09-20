<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use Illuminate\Support\Str;

final class PlatformSignedRequest
{
    /**
     * @return array<string, string>
     */
    public static function headers(string $method, string $path, string $rawBody, string $credential): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $rawBody),
        ]), $credential);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Ark-Installation-Id' => InstallationIdentity::uuid(),
            'X-Ark-Timestamp' => $timestamp,
            'X-Ark-Nonce' => $nonce,
            'X-Ark-Signature' => $signature,
        ];
    }
}
