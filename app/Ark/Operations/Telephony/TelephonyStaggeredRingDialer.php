<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
class TelephonyStaggeredRingDialer
{
    public function __construct(
        private readonly OutboundVoiceCallControl $twilio,
        private readonly TelephonyRingState $ringState,
    ) {}

    public function dialEndpoint(string $parentCallSid, int $endpointId): void
    {
        $state = $this->ringState->get($parentCallSid);

        if ($state === null || ($state['answered'] ?? false)) {
            return;
        }

        $endpoint = TelephonyEndpoint::query()->with('user')->find($endpointId);

        if ($endpoint === null || ! $endpoint->enabled) {
            return;
        }

        $to = $this->twilioToAddress($endpoint, $state['customer_caller_id'] ?? null);

        if ($to === null) {
            return;
        }

        $joinUrl = $endpoint->type === TelephonyEndpointType::Cell
            ? ''
            : route('webhooks.communications.twilio.voice.conference-join', [
                'conference' => $state['conference_name'],
                'parentCallSid' => $parentCallSid,
                'endpointId' => $endpointId,
            ]);

        $statusCallbackUrl = '';

        $outboundCallSid = $this->twilio->createOutboundCall(
            $state['shop_caller_id'],
            $to,
            $joinUrl,
            $statusCallbackUrl,
            timeout: TelephonyCallFlowSettings::fromShopSettings()->dialTimeoutSeconds(),
        );

        if ($outboundCallSid === null) {
            return;
        }

        $this->ringState->rememberOutboundCall($parentCallSid, $endpointId, $outboundCallSid);
    }

    private function twilioToAddress(TelephonyEndpoint $endpoint, ?string $customerCallerId = null): ?string
    {
        if ($endpoint->type === TelephonyEndpointType::Sip) {
            $destination = $endpoint->dialDestination();

            if ($destination === '') {
                return null;
            }

            return $this->sipUriWithCustomerHint($destination, $customerCallerId);
        }

        return PhoneNumber::toE164($endpoint->dialDestination());
    }

    private function sipUriWithCustomerHint(string $destination, ?string $customerCallerId): string
    {
        $customerE164 = PhoneNumber::toE164($customerCallerId);

        if ($customerE164 === null) {
            return $destination;
        }

        $separator = str_contains($destination, '?') ? '&' : '?';

        return $destination.$separator.'X-ARK-Caller='.rawurlencode($customerE164);
    }
}
