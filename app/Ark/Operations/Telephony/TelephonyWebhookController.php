<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyWebhookController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
        ProcessIncomingCallAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if (! TelephonyProgrammableVoiceGuard::isActive()) {
            return response(TelephonyProgrammableVoiceGuard::hangupTwiml(), 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        $provider = $providers->twilio();
        $payload = $provider->parseIncomingVoiceRequest($request);

        $result = $process->execute($payload);

        if ($result['session'] === null) {
            return response($provider->buildIncomingVoiceResponse($payload), 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        $flow = TelephonyIncomingCallFlow::forCurrentShop();

        $caller = $payload->normalizedFrom !== '' ? $payload->normalizedFrom : $payload->fromNumber;

        if (! $flow->shouldDispatchStaggeredRing($caller)) {
            app(TelephonyRingState::class)->initializeParallel(
                $payload->providerCallSid,
                $payload->normalizedFrom !== '' ? $payload->normalizedFrom : $result['session']->normalized_from,
            );
        }

        if ($flow->shouldDispatchStaggeredRing($caller)) {
            app(TelephonyStaggeredRingDispatcher::class)->dispatchForParentCall($payload->providerCallSid);
        }

        cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

        return response($provider->buildIncomingVoiceResponse($payload), 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
