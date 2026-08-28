<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyUnansweredVoicemailWebhookController
{
    public function __invoke(
        Request $request,
        string $parentCallSid,
        TwilioWebhookVerifier $verifier,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $flow = TelephonyIncomingCallFlow::forCurrentShop();

        return response($flow->buildVoicemailResponse(
            TelephonyCallFlowSettings::fromShopSettings()->voicemailGreeting(),
        ), 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
