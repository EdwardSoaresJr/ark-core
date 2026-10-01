<?php

namespace App\Console\Commands;

use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Console\Command;

class ReconcileStaleCallSessionsCommand extends Command
{
    protected $signature = 'comms:reconcile-stale-call-sessions';

    protected $description = 'Mark stale ringing call sessions missed when no terminal hosted fact arrived';

    public function handle(CallSessionQueue $queue): int
    {
        $queue->reconcileStaleLiveSessions();

        return self::SUCCESS;
    }
}
