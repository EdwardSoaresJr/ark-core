<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Jobs\RingTelephonyEndpointJob;

class TelephonyStaggeredRingExpander
{
    public function __construct(
        private readonly TelephonyRingGroup $ringGroup,
        private readonly TelephonyRingState $ringState,
        private readonly OutboundVoiceCallControl $twilio,
    ) {}

    public function expand(string $parentCallSid, int $maxDelaySeconds): void
    {
        if (! $this->twilio->configured()) {
            return;
        }

        $state = $this->ringState->get($parentCallSid);

        if ($state === null || ($state['answered'] ?? false)) {
            return;
        }

        $alreadyExpanded = (int) ($state['expanded_max_delay_seconds'] ?? -1);

        if ($maxDelaySeconds <= $alreadyExpanded) {
            return;
        }

        $endpoints = $this->ringGroup->endpointsForStaggeredTier($alreadyExpanded, $maxDelaySeconds);

        if ($endpoints->isEmpty()) {
            $this->ringState->markExpanded($parentCallSid, $maxDelaySeconds);

            return;
        }

        $this->ringState->markExpanded($parentCallSid, $maxDelaySeconds);

        foreach ($endpoints as $endpoint) {
            RingTelephonyEndpointJob::dispatch($parentCallSid, $endpoint->id);
        }
    }
}
