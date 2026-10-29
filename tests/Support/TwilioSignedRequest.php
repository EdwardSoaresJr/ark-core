<?php

use Illuminate\Testing\TestResponse;

/**
 * Posts a webhook the way Twilio does: signed over the URL and every sorted form parameter
 * with the configured auth token.
 *
 * @param  array<string, mixed>  $params
 * @param  array<string, string>  $headers
 */
function signedTwilioPost(string $url, array $params = [], array $headers = []): TestResponse
{
    $signed = [];
    foreach ($params as $key => $value) {
        if (! is_array($value)) {
            $signed[(string) $key] = (string) $value;
        }
    }
    ksort($signed, SORT_STRING);

    $data = $url;
    foreach ($signed as $key => $value) {
        $data .= $key.$value;
    }

    $signature = base64_encode(hash_hmac('sha1', $data, (string) config('services.twilio.auth_token'), true));

    return test()->post($url, $params, ['X-Twilio-Signature' => $signature] + $headers);
}
