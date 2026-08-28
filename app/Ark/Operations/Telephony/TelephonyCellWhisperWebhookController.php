<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyCellWhisperWebhookController
{
    public function __invoke(
        Request $request,
        string $parentCallSid,
        int $endpointId,
        TwilioWebhookVerifier $verifier,
        TelephonyCellWhisperFlow $whisper,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if ($parentCallSid === '' || $endpointId <= 0) {
            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>');
        }

        $state = app(TelephonyRingState::class)->get($parentCallSid);

        if (
            $state !== null
            && ($state['answered'] ?? false)
            && (int) ($state['answered_endpoint_id'] ?? 0) !== $endpointId
        ) {
            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>');
        }

        return $this->xml($whisper->whisperResponse($parentCallSid, $endpointId));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
