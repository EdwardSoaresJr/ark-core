<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelephonyCallbackAnswerWebhookController
{
    public function __invoke(
        Request $request,
        string $token,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
        TelephonyCallbackStore $callbackStore,
        ProcessOutboundCallAction $process,
        TelephonyOutboundCallerId $callerId,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $cachedTwiml = $callbackStore->findTwiml($token);

        if ($cachedTwiml !== null) {
            cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

            return $this->xml($cachedTwiml);
        }

        return $callbackStore->locked($token, function () use (
            $request,
            $token,
            $providers,
            $callbackStore,
            $process,
            $callerId,
        ): Response {
            $cachedTwiml = $callbackStore->findTwiml($token);

            if ($cachedTwiml !== null) {
                cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

                return $this->xml($cachedTwiml);
            }

            $intent = $callbackStore->find($token);

            if ($intent === null) {
                return $this->xml($this->sayResponse('This callback request has expired. Start a new callback from ARK.'));
            }

            $provider = $providers->twilio();
            $payload = $provider->parseCallbackAnswerRequest($request, $intent);
            $endpoint = TelephonyEndpoint::query()->find($intent->endpointId);

            $result = $process->execute($payload, $endpoint);
            $session = $result['session'];

            if ($session->owned_by_user_id === null && $intent->initiatedByUserId > 0) {
                $session->forceFill([
                    'owned_by_user_id' => $intent->initiatedByUserId,
                    'owned_at' => now(),
                ])->save();
            }

            if ($intent->repairOrderId !== null && $session->repair_order_id === null) {
                $session->forceFill(['repair_order_id' => $intent->repairOrderId])->save();
            }

            $twiml = $provider->buildCallbackCustomerDialResponse(
                $intent->customerE164,
                $callerId->resolve(),
            );

            $callbackStore->rememberTwiml($token, $twiml);
            $callbackStore->forget($token);

            cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

            return $this->xml($twiml);
        });
    }

    private function sayResponse(string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response><Say voice="alice">'.htmlspecialchars($message, ENT_XML1).'</Say></Response>';
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
