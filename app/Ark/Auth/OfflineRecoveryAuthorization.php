<?php

namespace App\Ark\Auth;

final class OfflineRecoveryAuthorization
{
    public const PURPOSE = 'staff.password_recovery';

    public const VERSION = 1;

    public const PREFIX = 'ARK2';

    /**
     * @return array{payload: string, signature: string}
     */
    public static function splitArtifact(string $artifact): array
    {
        $parts = explode('.', trim($artifact));
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw new \InvalidArgumentException('Authorization format is invalid.');
        }

        $payload = self::base64UrlDecode($parts[1]);
        $signature = self::base64UrlDecode($parts[2]);

        if ($payload === '' || $signature === '') {
            throw new \InvalidArgumentException('Authorization format is invalid.');
        }

        return ['payload' => $payload, 'signature' => $signature];
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $encoded): string
    {
        $padded = strtr($encoded, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? '' : $decoded;
    }
}
