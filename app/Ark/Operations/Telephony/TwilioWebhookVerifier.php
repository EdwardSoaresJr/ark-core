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

        $params = $this->signedParameters($request);
        ksort($params, SORT_STRING);
        $data = $request->getSchemeAndHttpHost().$request->getRequestUri();
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $given);
    }

    /**
     * Twilio signs the form body it sent, so trimming or empty-to-null middleware must not change it.
     *
     * @return array<string, string>
     */
    private function signedParameters(Request $request): array
    {
        if ($request->getMethod() !== 'POST') {
            return [];
        }

        $raw = (string) $request->getContent();
        if ($raw !== '' && str_contains(strtolower((string) $request->header('Content-Type', '')), 'application/x-www-form-urlencoded')) {
            return self::parseFormBody($raw);
        }

        $params = [];
        foreach ($request->request->all() as $key => $value) {
            if (! is_string($key) || is_array($value)) {
                continue;
            }
            $params[$key] = (string) $value;
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public static function parseFormBody(string $raw): array
    {
        $params = [];
        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $params[urldecode($key)] = urldecode($value);
        }

        return $params;
    }
}
