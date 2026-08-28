<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyStaggeredExpandWebhookController
{
    public function __invoke(
        Request $request,
        string $parentCallSid,
        int $maxDelay,
        TwilioWebhookVerifier $verifier,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if ($parentCallSid === '' || $maxDelay < 0) {
            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
        }

        $flow = TelephonyIncomingCallFlow::forCurrentShop();

        return $this->xml($flow->buildStaggeredExpandResponse($parentCallSid, $maxDelay));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
