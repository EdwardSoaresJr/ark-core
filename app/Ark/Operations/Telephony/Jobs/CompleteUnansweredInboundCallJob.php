<?php

namespace App\Ark\Operations\Telephony\Jobs;

use App\Ark\Operations\Telephony\TelephonyRingState;
use App\Ark\Operations\Telephony\OutboundVoiceCallControl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompleteUnansweredInboundCallJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $parentCallSid,
    ) {}

    public function handle(TelephonyRingState $ringState, OutboundVoiceCallControl $twilio): void
    {
        if (! $twilio->configured()) {
            return;
        }

        $state = $ringState->get($this->parentCallSid);

        if ($state === null || ($state['answered'] ?? false)) {
            return;
        }

        $twilio->redirectCall(
            $this->parentCallSid,
            '',
        );
    }
}
