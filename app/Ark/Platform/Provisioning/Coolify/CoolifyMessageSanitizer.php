<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final class CoolifyMessageSanitizer
{
    public static function sanitize(?string $message): string
    {
        if ($message === null || $message === '') {
            return '';
        }

        $clean = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $clean = preg_replace('/Authorization:\s*\S+/i', 'Authorization: [redacted]', $clean) ?? $clean;
        $token = (string) config('ark-platform.coolify.token', '');
        if ($token !== '') {
            $clean = str_replace($token, '[redacted]', $clean);
        }

        return $clean;
    }
}
