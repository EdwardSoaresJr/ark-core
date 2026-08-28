<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyRingLegStatusWebhookController
{
    public function __invoke(
        Request $request,
        string $parentCallSid,
        int $endpointId,
        TwilioWebhookVerifier $verifier,
        TelephonyRingLegStatusHandler $ringLegStatusHandler,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $ringLegStatusHandler->handleRingLeg($request, $parentCallSid, $endpointId);

        return response('', 204);
    }
}
