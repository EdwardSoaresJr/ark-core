<?php

namespace App\Ark\Operations\Messaging;

/**
 * Twilio request signature: HMAC-SHA1 of the exact URL plus sorted POST fields.
 */
final class TwilioRequestSignature
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function sign(string $url, array $params, string $authToken): string
    {
        $data = $url;
        ksort($params);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function valid(string $url, array $params, string $authToken, string $signature): bool
    {
        if ($authToken === '' || $signature === '') {
            return false;
        }

        $expected = self::sign($url, $params, $authToken);

        return hash_equals($expected, $signature);
    }
}
