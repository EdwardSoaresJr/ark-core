<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TelephonyClientIncomingWebhookController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if (! TelephonyProgrammableVoiceGuard::isActive()) {
            return $this->xml(TelephonyProgrammableVoiceGuard::hangupTwiml());
        }

        $provider = $providers->twilio();

        $from = trim((string) $request->input('From', ''));
        $parsed = MobileVoiceIdentity::parse(str_replace('client:', '', $from));

        if ($parsed === null) {
            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response><Say voice="alice">Unable to connect this call.</Say></Response>');
        }

        cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

        return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
