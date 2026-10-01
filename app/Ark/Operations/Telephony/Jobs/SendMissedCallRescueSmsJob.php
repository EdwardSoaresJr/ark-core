<?php

namespace App\Ark\Operations\Telephony\Jobs;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\SendMissedCallRescueSmsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMissedCallRescueSmsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $callSessionId,
    ) {}

    public function handle(SendMissedCallRescueSmsAction $action): void
    {
        $session = CallSession::query()->find($this->callSessionId);

        if ($session === null) {
            return;
        }

        $action->execute($session);
    }
}
