<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\Jobs\CompleteUnansweredInboundCallJob;
use App\Ark\Operations\Telephony\Jobs\ExpandStaggeredRingJob;
use App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob;

class TelephonyStaggeredRingDispatcher
{
    public function __construct(
        private readonly TelephonyRingGroup $ringGroup,
        private readonly TelephonyRingState $ringState,
        private readonly OutboundVoiceCallControl $twilio,
        private readonly TelephonyOutboundCallerId $outboundCallerId,
    ) {}

    public function dispatchForParentCall(string $parentCallSid): void
    {
        if (! $this->twilio->configured()) {
            return;
        }

        $settings = ShopSettings::current();
        $endpoints = $this->ringGroup->endpointsForIncomingRing($settings);

        if ($endpoints->isEmpty()) {
            return;
        }

        $session = CallSession::query()
            ->where('provider_call_sid', $parentCallSid)
            ->first(['from_number', 'normalized_from']);

        $customerCallerId = PhoneNumber::normalize($session?->normalized_from)
            ?? PhoneNumber::normalize($session?->from_number);

        $shopCallerId = $this->outboundCallerId->resolve($settings);

        if ($shopCallerId === null) {
            return;
        }

        $this->ringState->initialize(
            $parentCallSid,
            TelephonyConferenceName::forParentCall($parentCallSid),
            $shopCallerId,
            [],
            is_string($customerCallerId) ? $customerCallerId : null,
        );

        foreach ($this->ringGroup->endpointsForStaggeredTier(-1, 0, $settings) as $endpoint) {
            RingTelephonyEndpointJob::dispatch($parentCallSid, $endpoint->id);
        }

        $this->ringState->markExpanded($parentCallSid, 0);

        $flow = TelephonyCallFlowSettings::fromShopSettings($settings);
        $leadSeconds = $flow->staggeredRingLeadSeconds();
        $dialTimeoutSeconds = $flow->dialTimeoutSeconds();

        CompleteUnansweredInboundCallJob::dispatch($parentCallSid)
            ->delay(now()->addSeconds($dialTimeoutSeconds));

        foreach ($this->ringGroup->staggeredDelayTiers($settings) as $delaySeconds) {
            ExpandStaggeredRingJob::dispatch($parentCallSid, (int) $delaySeconds)
                ->delay(now()->addSeconds($leadSeconds + (int) $delaySeconds));
        }
    }
}
