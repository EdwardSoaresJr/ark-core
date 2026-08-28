<?php

namespace App\Ark\Operations\Telephony\Providers;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyCallbackIntent;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyIncomingCallFlow;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonySipUri;
use Illuminate\Http\Request;

class TwilioTelephonyProvider implements TelephonyProvider
{
    public function type(): TelephonyProviderType
    {
        return TelephonyProviderType::Twilio;
    }

    public function parseIncomingVoiceRequest(Request $request): IncomingCallPayload
    {
        $from = trim((string) $request->input('From', ''));
        $to = trim((string) $request->input('To', ''));
        $callSid = trim((string) $request->input('CallSid', ''));

        if ($callSid === '') {
            $callSid = 'sim-'.uniqid();
        }

        $normalizedFrom = PhoneNumber::normalize($from) ?? PhoneNumber::digits($from);
        $normalizedTo = PhoneNumber::normalize($to);

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $callSid,
            fromNumber: $from !== '' ? $from : $normalizedFrom,
            toNumber: $to,
            normalizedFrom: $normalizedFrom,
            normalizedTo: $normalizedTo,
            status: CallSessionStatus::fromTwilioStatus($request->input('CallStatus')),
            rawPayload: $request->all(),
        );
    }

    public function buildIncomingVoiceResponse(IncomingCallPayload $payload): string
    {
        $caller = $payload->normalizedFrom !== '' ? $payload->normalizedFrom : $payload->fromNumber;

        return TelephonyIncomingCallFlow::forCurrentShop()->buildResponse(
            $payload->providerCallSid,
            $caller,
        );
    }

    public function parseSipOutboundVoiceRequest(Request $request): IncomingCallPayload
    {
        $from = trim((string) $request->input('From', ''));
        $to = trim((string) $request->input('To', ''));
        $callSid = trim((string) $request->input('CallSid', ''));

        if ($callSid === '') {
            $callSid = 'sim-'.uniqid();
        }

        $dialed = TelephonySipUri::dialedNumber($to);
        $normalizedTo = $dialed !== null ? PhoneNumber::normalize($dialed) : null;
        $e164To = PhoneNumber::toE164($dialed ?? $to) ?? $to;

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $callSid,
            fromNumber: $from,
            toNumber: $e164To,
            normalizedFrom: TelephonySipUri::normalizeForMatch($from),
            normalizedTo: $normalizedTo,
            status: CallSessionStatus::fromTwilioStatus($request->input('CallStatus')),
            rawPayload: $request->all(),
            direction: CallSessionDirection::Outbound,
        );
    }

    public function parseCallbackAnswerRequest(Request $request, TelephonyCallbackIntent $intent): IncomingCallPayload
    {
        $from = trim((string) $request->input('From', ''));
        $callSid = trim((string) $request->input('CallSid', ''));

        if ($callSid === '') {
            $callSid = 'sim-'.uniqid();
        }

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $callSid,
            fromNumber: $from,
            toNumber: $intent->customerE164,
            normalizedFrom: PhoneNumber::normalize($from) ?? PhoneNumber::digits($from),
            normalizedTo: $intent->normalizedCustomerPhone,
            status: CallSessionStatus::fromTwilioStatus($request->input('CallStatus')),
            rawPayload: $request->all(),
            direction: CallSessionDirection::Outbound,
        );
    }

    public function buildCallbackCustomerDialResponse(string $customerE164, ?string $callerIdE164): string
    {
        if ($callerIdE164 === null || $callerIdE164 === '') {
            return $this->sayResponse('Save your shop Twilio number in Settings before placing callbacks.');
        }

        $statusCallback = route('webhooks.communications.twilio.voice.status');
        $recordingCallback = route('webhooks.communications.twilio.voice.recording');
        $statusCallbackAttributes = ' statusCallback="'.htmlspecialchars($statusCallback, ENT_XML1).'"'
            .' statusCallbackEvent="initiated ringing answered completed"'
            .' statusCallbackMethod="POST"';

        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $disclaimer = $flow->recordOutboundCalls()
            ? '<Say voice="alice">'.htmlspecialchars($flow->recordingDisclaimer(), ENT_XML1).'</Say>'
            : '';
        $recordAttributes = '';

        if ($flow->recordOutboundCalls()) {
            $recordAttributes = ' record="record-from-answer"'
                .' recordingStatusCallback="'.htmlspecialchars($recordingCallback, ENT_XML1).'"'
                .' recordingStatusCallbackMethod="POST"';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'.$disclaimer
            .'<Dial callerId="'.htmlspecialchars($callerIdE164, ENT_XML1).'"'.$statusCallbackAttributes.$recordAttributes.'>'
            .'<Number>'.htmlspecialchars($customerE164, ENT_XML1).'</Number>'
            .'</Dial></Response>';
    }

    public function buildSipOutboundVoiceResponse(
        IncomingCallPayload $payload,
        ?TelephonyEndpoint $endpoint,
        ?string $callerIdE164,
    ): string {
        if ($endpoint === null) {
            return $this->sayResponse('This phone is not authorized for outbound calling.');
        }

        if ($callerIdE164 === null || $callerIdE164 === '') {
            return $this->sayResponse('Save your shop Twilio number in Settings before placing outbound calls.');
        }

        $destination = PhoneNumber::toE164($payload->toNumber)
            ?? PhoneNumber::toE164($payload->normalizedTo ?? '');

        if ($destination === null) {
            return $this->sayResponse('Could not dial that number. Use a ten digit US phone number.');
        }

        $statusCallback = route('webhooks.communications.twilio.voice.status');
        $recordingCallback = route('webhooks.communications.twilio.voice.recording');
        $statusCallbackAttributes = ' statusCallback="'.htmlspecialchars($statusCallback, ENT_XML1).'"'
            .' statusCallbackEvent="initiated ringing answered completed"'
            .' statusCallbackMethod="POST"';

        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $disclaimer = $flow->recordOutboundCalls()
            ? '<Say voice="alice">'.htmlspecialchars($flow->recordingDisclaimer(), ENT_XML1).'</Say>'
            : '';
        $recordAttributes = '';

        if ($flow->recordOutboundCalls()) {
            $recordAttributes = ' record="record-from-answer"'
                .' recordingStatusCallback="'.htmlspecialchars($recordingCallback, ENT_XML1).'"'
                .' recordingStatusCallbackMethod="POST"';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'.$disclaimer
            .'<Dial callerId="'.htmlspecialchars($callerIdE164, ENT_XML1).'"'.$statusCallbackAttributes.$recordAttributes.'>'
            .'<Number>'.htmlspecialchars($destination, ENT_XML1).'</Number>'
            .'</Dial></Response>';
    }

    public function parseDialCompleteRequest(Request $request): ?IncomingCallPayload
    {
        $parentCallSid = trim((string) $request->input('CallSid', ''));
        $dialStatus = strtolower(trim((string) $request->input('DialCallStatus', '')));

        if ($parentCallSid === '' || $dialStatus === '') {
            return null;
        }

        if (! in_array($dialStatus, ['completed', 'busy', 'no-answer', 'canceled', 'failed'], true)) {
            return null;
        }

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $parentCallSid,
            fromNumber: '',
            toNumber: '',
            normalizedFrom: '',
            normalizedTo: null,
            status: CallSessionStatus::fromTwilioStatus($dialStatus),
            rawPayload: $request->all(),
        );
    }

    private function sayResponse(string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response><Say voice="alice">'.htmlspecialchars($message, ENT_XML1).'</Say></Response>';
    }
}
