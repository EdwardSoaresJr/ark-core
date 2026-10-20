<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;

final class TwilioWebhookVerifier
{
    public function isValid(Request $request): bool
    {
        $token = trim((string) config('services.twilio.auth_token', ''));

        if ($token === '') {
            return true;
        }

        $given = trim((string) $request->header('X-Twilio-Signature', ''));

        if ($given === '') {
            return false;
        }

        $params = [];
        foreach ($request->request->all() as $key => $value) {
            if (! is_string($key) || is_array($value)) {
                continue;
            }
            $params[$key] = (string) $value;
        }
        ksort($params);

        $data = $request->fullUrl();
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $given);
    }
}
