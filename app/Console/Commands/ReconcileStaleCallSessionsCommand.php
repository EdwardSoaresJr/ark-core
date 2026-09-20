<?php

namespace App\Console\Commands;

use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Console\Command;

class ReconcileStaleCallSessionsCommand extends Command
{
    protected $signature = 'comms:reconcile-stale-call-sessions';

    protected $description = 'Close out stale ringing and answered call sessions that never received a terminal callback';

    public function handle(CallSessionQueue $queue): int
    {
        $queue->reconcileStaleLiveSessions();

        return self::SUCCESS;
    }
}
