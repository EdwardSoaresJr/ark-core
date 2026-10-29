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

        $outcome = $process->execute($request, voicemail: $request->boolean('voicemail'));

        if ($outcome === ProcessCallRecordingOutcome::Unmatched) {
            return response('', 404);
        }

        return response('', 204);
    }
}
