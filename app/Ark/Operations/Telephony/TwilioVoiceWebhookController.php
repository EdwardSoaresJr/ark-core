<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class TwilioVoiceWebhookController
{
    public function incoming(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessIncomingCallAction $incoming,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        Cache::put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now()->toIso8601String(), now()->addDay());

        $payload = TwilioCallPayload::fromRequest($request);
        if ($payload->providerCallSid !== '') {
            $incoming->execute($payload);
        }

        $twiml = TelephonyIncomingCallFlow::forCurrentShop()->buildResponse(
            $payload->providerCallSid,
            $payload->normalizedFrom,
        );

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function status(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessCallStatusAction $status,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $payload = $this->statusPayload($request);
        if ($payload !== null && $payload->providerCallSid !== '') {
            $status->execute($payload);
        }

        return response('', 204);
    }

    private function statusPayload(Request $request): ?IncomingCallPayload
    {
        $parent = trim((string) $request->input('ParentCallSid', ''));
        $direction = strtolower((string) $request->input('Direction', ''));
        $callStatus = strtolower((string) $request->input('CallStatus', ''));

        if ($parent !== '' && str_starts_with($direction, 'outbound')) {
            if (! in_array($callStatus, ['in-progress', 'answered'], true)) {
                return null;
            }

            $request->merge([
                'CallSid' => $parent,
                'CallStatus' => 'in-progress',
                'Direction' => 'inbound',
            ]);
        }

        return TwilioCallPayload::fromRequest($request);
    }

    public function dialComplete(
        Request $request,
        TwilioWebhookVerifier $verifier,
        ProcessCallStatusAction $status,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $dialStatus = strtolower(trim((string) $request->input('DialCallStatus', '')));
        if (in_array($dialStatus, ['completed', 'answered'], true)) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        if (in_array($dialStatus, ['no-answer', 'busy', 'canceled', 'failed'], true)) {
            $request->merge([
                'CallStatus' => $dialStatus,
                'Direction' => 'inbound',
            ]);
            $payload = TwilioCallPayload::fromRequest($request, CallSessionDirection::Inbound);
            if ($payload->providerCallSid !== '') {
                $status->execute($payload);
            }
        }

        $flow = TelephonyIncomingCallFlow::forCurrentShop();
        $twiml = $flow->buildVoicemailResponse(
            TelephonyCallFlowSettings::fromShopSettings()->voicemailGreeting(),
        );

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function conferenceJoin(Request $request, TwilioWebhookVerifier $verifier): Response
    {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $name = trim((string) $request->query('conference', ''));
        $safe = htmlspecialchars($name, ENT_XML1);

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response><Dial><Conference>'.$safe.'</Conference></Dial></Response>',
            200,
            ['Content-Type' => 'text/xml'],
        );
    }

    public function ringStatus(
        Request $request,
        TwilioWebhookVerifier $verifier,
        TelephonyRingLegStatusHandler $handler,
        string $parentCallSid,
        int $endpointId,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $handler->handleRingLeg($request, $parentCallSid, $endpointId);

        return response('', 204);
    }
}
