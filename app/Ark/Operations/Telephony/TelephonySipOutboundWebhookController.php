<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonySipOutboundWebhookController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
        TelephonyEndpointMatcher $endpointMatcher,
        TelephonyOutboundCallerId $callerId,
        ProcessOutboundCallAction $process,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if (! TelephonyProgrammableVoiceGuard::isActive()) {
            return $this->twimlResponse(TelephonyProgrammableVoiceGuard::hangupTwiml());
        }

        $provider = $providers->twilio();

        if (! app(ShopIntegrationCredentials::class)->twilioConfigured()) {
            return $this->twimlResponse($provider->buildSipOutboundVoiceResponse(
                $provider->parseSipOutboundVoiceRequest($request),
                null,
                null,
            ));
        }

        $payload = $provider->parseSipOutboundVoiceRequest($request);
        $endpoint = $endpointMatcher->resolveSipOrigin($payload->fromNumber);

        $process->execute($payload, $endpoint);

        cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

        return $this->twimlResponse($provider->buildSipOutboundVoiceResponse(
            $payload,
            $endpoint,
            $callerId->resolve(),
        ));
    }

    private function twimlResponse(string $twiml): Response
    {
        return response($twiml, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
