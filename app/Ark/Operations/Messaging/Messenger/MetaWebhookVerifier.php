<?php

namespace App\Ark\Operations\Messaging\Messenger;

use Illuminate\Http\Request;

class MetaWebhookVerifier
{
    public function verifySubscription(Request $request, string $expectedToken): ?string
    {
        if ($request->query('hub_mode') !== 'subscribe') {
            return null;
        }

        $token = (string) $request->query('hub_verify_token', '');

        if ($token === '' || ! hash_equals($expectedToken, $token)) {
            return null;
        }

        return (string) $request->query('hub_challenge', '');
    }

    public function isValidSignature(Request $request, ?string $appSecret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        $hasSignature = is_string($signature) && str_starts_with($signature, 'sha256=');

        if (app()->environment('local', 'testing') && ! filled($appSecret)) {
            return true;
        }

        if (! filled($appSecret)) {
            return false;
        }

        if (app()->environment('testing') && ! $hasSignature) {
            return true;
        }

        if (! $hasSignature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $appSecret);

        return hash_equals($expected, $signature);
    }
}
