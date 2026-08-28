<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceConnectStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TelephonyClientOutboundWebhookController
{
    public function __invoke(
        Request $request,
        TelephonyProviderManager $providers,
        TwilioWebhookVerifier $verifier,
        MobileVoiceConnectStore $connectStore,
        ProcessOutboundCallAction $process,
        TelephonyOutboundCallerId $callerId,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        if (! TelephonyProgrammableVoiceGuard::isActive()) {
            return $this->xml(TelephonyProgrammableVoiceGuard::hangupTwiml());
        }

        $provider = $providers->twilio();

        $connectToken = trim((string) ($request->input('connect_token') ?? $request->input('ConnectToken') ?? ''));

        if ($connectToken === '') {
            return $this->xml($provider->buildCallbackCustomerDialResponse('', null));
        }

        $intent = $connectStore->find($connectToken);

        if ($intent === null) {
            return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response><Say voice="alice">This call request has expired. Start a new call from ARK.</Say></Response>');
        }

        $callSid = trim((string) $request->input('CallSid', ''));

        if ($callSid === '') {
            $callSid = 'sim-'.uniqid();
        }

        $from = trim((string) $request->input('From', ''));
        $payload = $provider->parseCallbackAnswerRequest($request, new TelephonyCallbackIntent(
            initiatedByUserId: $intent->initiatedByUserId,
            endpointId: $intent->endpointId,
            customerE164: $intent->customerE164,
            normalizedCustomerPhone: $intent->normalizedCustomerPhone,
            customerId: $intent->customerId,
            repairOrderId: $intent->repairOrderId,
        ));

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

        $connectStore->forget($connectToken);

        cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addDays(30));

        return $this->xml($provider->buildCallbackCustomerDialResponse(
            $intent->customerE164,
            $callerId->resolve(),
        ));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
