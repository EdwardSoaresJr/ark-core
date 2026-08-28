<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyDialCompleteWebhookController
{
    public function __invoke(
        Request $request,
        TwilioWebhookVerifier $verifier,
        TelephonyProviderManager $providers,
        ProcessCallStatusAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $provider = $providers->twilio();

        $payload = $provider->parseDialCompleteRequest($request);

        $dialStatus = strtolower(trim((string) $request->input('DialCallStatus', '')));
        $parentCallSid = trim((string) $request->input('CallSid', ''));
        $wasAnswered = $this->wasCallAnswered($parentCallSid);

        if ($payload !== null) {
            $process->execute($payload);
        }

        if ($dialStatus === 'completed' && $wasAnswered) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>', 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        $flow = TelephonyIncomingCallFlow::forCurrentShop();

        return response($flow->buildVoicemailResponse(
            TelephonyCallFlowSettings::fromShopSettings()->voicemailGreeting(),
        ), 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    private function wasCallAnswered(string $parentCallSid): bool
    {
        if ($parentCallSid === '') {
            return false;
        }

        $state = app(TelephonyRingState::class)->get($parentCallSid);

        if ($state !== null) {
            return ($state['answered'] ?? false) === true;
        }

        $session = CallSession::query()
            ->where('provider_call_sid', $parentCallSid)
            ->first(['answered_at']);

        return $session?->answered_at !== null;
    }
}
