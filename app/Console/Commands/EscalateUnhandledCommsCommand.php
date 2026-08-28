<?php

namespace App\Console\Commands;

use App\Ark\Operations\Communications\CommsEscalationRunner;
use Illuminate\Console\Command;

class EscalateUnhandledCommsCommand extends Command
{
    protected $signature = 'comms:escalate-unhandled';

    protected $description = 'SMS on-duty advisors about calls, texts, and website leads still unhandled after the escalation delay';

    public function handle(CommsEscalationRunner $runner): int
    {
        $sent = $runner->run();

        if ($sent > 0) {
            $this->info("Sent {$sent} comms escalation(s).");
        }

        return self::SUCCESS;
    }
}
