<?php

namespace App\Ark\Operations\Telephony\Jobs;

use App\Ark\Operations\Telephony\TelephonyStaggeredRingDialer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RingTelephonyEndpointJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $parentCallSid,
        public readonly int $endpointId,
    ) {}

    public function handle(TelephonyStaggeredRingDialer $dialer): void
    {
        $dialer->dialEndpoint($this->parentCallSid, $this->endpointId);
    }
}
