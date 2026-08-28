<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeCallSessionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly int $callSessionId,
    ) {}

    public function handle(CallSessionAnalyzer $analyzer): void
    {
        $callSession = CallSession::query()->find($this->callSessionId);

        if ($callSession === null) {
            return;
        }

        $analyzer->analyze($callSession);
    }
}
