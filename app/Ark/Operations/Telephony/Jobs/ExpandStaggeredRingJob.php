<?php

namespace App\Ark\Operations\Telephony\Jobs;

use App\Ark\Operations\Telephony\TelephonyStaggeredRingExpander;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpandStaggeredRingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $parentCallSid,
        public readonly int $maxDelaySeconds,
    ) {}

    public function handle(TelephonyStaggeredRingExpander $expander): void
    {
        $expander->expand($this->parentCallSid, $this->maxDelaySeconds);
    }
}
