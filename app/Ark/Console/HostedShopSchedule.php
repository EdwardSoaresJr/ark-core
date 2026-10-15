<?php

namespace App\Ark\Console;

use Illuminate\Console\Scheduling\Schedule;

/**
 * A hosted release binds this when no shop is selected.
 * Standalone Core leaves it unbound and schedules against its own database.
 */
interface HostedShopSchedule
{
    public function register(Schedule $schedule): void;
}
