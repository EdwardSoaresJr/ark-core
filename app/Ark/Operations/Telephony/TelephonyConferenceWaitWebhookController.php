<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyConferenceWaitWebhookController
{
    public function __invoke(Request $request, TwilioWebhookVerifier $verifier): Response
    {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response><Pause length="3600"/></Response>',
            200,
            ['Content-Type' => 'text/xml'],
        );
    }
}
