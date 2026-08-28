<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Http\Request;

class TwilioWebhookVerifier
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function isValid(Request $request): bool
    {
        $authToken = $this->credentials->twilioAuthToken();
        $signature = $request->header('X-Twilio-Signature');
        $hasSignature = is_string($signature) && $signature !== '';

        if (app()->environment('local', 'testing') && ! filled($authToken)) {
            return true;
        }

        if (! filled($authToken)) {
            return false;
        }

        if (app()->environment('testing') && ! $hasSignature && ! filled($authToken)) {
            return true;
        }

        if (! $hasSignature) {
            return false;
        }

        $url = $this->signedRequestUrl($request);
        $params = $request->post();
        ksort($params);

        $data = $url;

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $data .= $key.(string) $value;
        }

        $computed = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($computed, $signature);
    }

    private function signedRequestUrl(Request $request): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            return $appUrl.$request->getRequestUri();
        }

        return $request->fullUrl();
    }
}
