<?php

namespace App\Console\Commands;

use App\Ark\Operations\Labor\EnsureAutoClockSessionsAction;
use App\Ark\Operations\Labor\MarkOvernightOpenSessionsAction;
use Illuminate\Console\Command;

class SyncTechnicianTimeClockCommand extends Command
{
    protected $signature = 'time-clock:sync-auto';

    protected $description = 'Flip overnight open punches to needs-resolution and materialize/close auto-clock workdays';

    public function handle(
        MarkOvernightOpenSessionsAction $markOvernight,
        EnsureAutoClockSessionsAction $ensureAuto,
    ): int {
        $markOvernight->handle();
        $ensureAuto->handle();

        return self::SUCCESS;
    }
}
