<?php

namespace App\Ark\Dragon\Agent;

final class DragonObservationSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrub(array $payload): array
    {
        $blockedKeys = [
            'password', 'secret', 'api_key', 'apikey', 'token', 'authorization',
            'card', 'cvv', 'pan', 'openai', 'sql', 'connection', 'email', 'phone',
            'customer_name', 'customer_label', 'first_name', 'last_name',
        ];

        $walk = function ($node) use (&$walk, $blockedKeys) {
            if (! is_array($node)) {
                if (is_string($node) && preg_match('/sk-[A-Za-z0-9]{10,}/', $node)) {
                    return '[redacted]';
                }

                return $node;
            }

            $out = [];
            foreach ($node as $key => $value) {
                $k = strtolower((string) $key);
                foreach ($blockedKeys as $blocked) {
                    if (str_contains($k, $blocked)) {
                        continue 2;
                    }
                }
                $out[$key] = $walk($value);
            }

            return $out;
        };

        /** @var array<string, mixed> $clean */
        $clean = $walk($payload);

        return $clean;
    }

    public static function summarize(array $payload, int $max = 400): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return mb_substr((string) $json, 0, $max);
    }
}
