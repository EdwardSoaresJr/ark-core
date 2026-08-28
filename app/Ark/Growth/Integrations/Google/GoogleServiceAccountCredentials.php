<?php

namespace App\Ark\Growth\Integrations\Google;

use InvalidArgumentException;

final class GoogleServiceAccountCredentials
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(string $json): array
    {
        $json = trim($json);

        if ($json === '') {
            throw new InvalidArgumentException('Paste the Google service account JSON first.');
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Service account JSON must be valid JSON.');
        }

        if (! filled($decoded['client_email'] ?? null) || ! filled($decoded['private_key'] ?? null)) {
            throw new InvalidArgumentException('Service account JSON must include client_email and private_key.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tryParse(?string $json): ?array
    {
        $json = trim((string) $json);

        if ($json === '') {
            return null;
        }

        return self::parse($json);
    }
}
