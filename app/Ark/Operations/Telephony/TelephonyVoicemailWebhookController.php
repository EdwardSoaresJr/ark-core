<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyVoicemailWebhookController
{
    public function __invoke(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessCallRecordingAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $process->execute($request, voicemail: true);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
