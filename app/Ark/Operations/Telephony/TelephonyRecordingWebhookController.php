<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyRecordingWebhookController
{
    public function __invoke(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessCallRecordingAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $process->execute($request, voicemail: false);

        return response('', 204);
    }
}
