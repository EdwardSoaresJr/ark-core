<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyStatusWebhookController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
        TelephonyRingLegStatusHandler $ringLegStatusHandler,
        ProcessCallStatusAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if ($ringLegStatusHandler->handle($request)) {
            return response('', 204);
        }

        $provider = $providers->twilio();
        $payload = $provider->parseIncomingVoiceRequest($request);

        $process->execute($payload);

        return response('', 204);
    }
}
