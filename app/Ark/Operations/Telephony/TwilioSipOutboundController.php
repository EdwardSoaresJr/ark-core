<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Outbound calls from registered desk phones on the shop's Twilio SIP domain. Handled entirely in Core:
 * the SIP domain's voice URL points here, and no other service is involved in placing the call.
 */
final class TwilioSipOutboundController
{
    public function dial(
        Request $request,
        TwilioWebhookVerifier $verifier,
        TelephonyEndpointMatcher $endpoints,
        TelephonyOutboundCallerId $callerIds,
        ProcessOutboundCallAction $outbound,
    ): Response {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $callSid = trim((string) $request->input('CallSid', ''));
        $origin = $endpoints->resolveSipOrigin((string) $request->input('From', ''));

        if ($origin === null) {
            Log::warning('telephony.sip_outbound.unknown_phone', ['call_sid' => $callSid]);

            return $this->say('This phone is not set up to place calls.');
        }

        $callerId = $callerIds->resolve();

        if ($callerId === null) {
            return $this->say('Outbound calling is not set up for this shop yet.');
        }

        $destination = TelephonySipOutboundDestination::resolve((string) $request->input('To', ''), $callerId);

        if ($destination['kind'] === 'rejected') {
            return $this->say((string) $destination['reason']);
        }

        if ($callSid !== '') {
            $outbound->execute(new IncomingCallPayload(
                provider: TelephonyProviderType::Twilio,
                providerCallSid: $callSid,
                fromNumber: (string) $request->input('From', ''),
                toNumber: (string) $destination['number'],
                normalizedFrom: PhoneNumber::normalize($callerId) ?? '',
                normalizedTo: PhoneNumber::normalize($destination['number']),
                status: CallSessionStatus::Ringing,
                rawPayload: $request->all(),
                direction: CallSessionDirection::Outbound,
            ), $origin);
        }

        return $this->twiml($this->dialVerb($destination['number'], $callerId, $destination['kind'] === 'emergency'));
    }

    public function complete(Request $request, TwilioWebhookVerifier $verifier, ProcessCallStatusAction $status): Response
    {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $dialStatus = strtolower(trim((string) $request->input('DialCallStatus', '')));
        $callStatus = match ($dialStatus) {
            'answered', 'completed' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'canceled' => 'canceled',
            default => 'failed',
        };

        $request->merge(['CallStatus' => $callStatus, 'Direction' => 'outbound-api']);
        $payload = TwilioCallPayload::fromRequest($request, CallSessionDirection::Outbound);

        if ($payload->providerCallSid !== '') {
            $status->execute($payload);
        }

        return match ($dialStatus) {
            'busy' => $this->say('The line is busy.'),
            'no-answer' => $this->say('There was no answer.'),
            'failed' => $this->say('The call could not be completed.'),
            default => $this->twiml('<Hangup/>'),
        };
    }

    public function notice(Request $request, TwilioWebhookVerifier $verifier): Response
    {
        if (! $verifier->isValid($request)) {
            return response('Invalid signature.', 401);
        }

        $disclaimer = TelephonyCallFlowSettings::fromShopSettings()->recordingDisclaimer();

        return $this->twiml($disclaimer === '' ? '' : '<Say voice="alice">'.htmlspecialchars($disclaimer, ENT_XML1).'</Say>');
    }

    private function dialVerb(string $number, string $callerId, bool $emergency): string
    {
        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $record = $flow->recordOutboundCalls() && ! $emergency;

        // Status callbacks belong on <Number>; Twilio rejects them as <Dial> attributes.
        $attributes = ' callerId="'.$this->attr($callerId).'"'
            .' answerOnBridge="true"'
            .' timeout="'.$flow->dialTimeoutSeconds().'"'
            .' action="'.$this->attr(route('webhooks.communications.twilio.voice.sip-outbound-complete')).'"';

        if ($record) {
            $attributes .= ' record="record-from-answer-dual"'
                .' recordingStatusCallback="'.$this->attr(route('webhooks.communications.twilio.voice.recording')).'"'
                .' recordingStatusCallbackMethod="POST"';
        }

        $number = '<Number'
            .' statusCallback="'.$this->attr(route('webhooks.communications.twilio.voice.status')).'"'
            .' statusCallbackEvent="initiated ringing answered completed" statusCallbackMethod="POST"'
            .($record ? ' url="'.$this->attr(route('webhooks.communications.twilio.voice.sip-outbound-notice')).'"' : '')
            .'>'.$this->attr($number).'</Number>';

        return '<Dial'.$attributes.'>'.$number.'</Dial>';
    }

    private function say(string $message): Response
    {
        return $this->twiml('<Say voice="alice">'.htmlspecialchars($message, ENT_XML1).'</Say><Hangup/>');
    }

    private function twiml(string $verbs): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response>'.$verbs.'</Response>', 200, ['Content-Type' => 'text/xml']);
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES);
    }
}
