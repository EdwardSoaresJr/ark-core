<?php

namespace App\Console\Commands;

use App\Ark\Operations\Learn\BookStack\BookStackLockdown;
use Illuminate\Console\Command;

class ArkBookStackLockdownCommand extends Command
{
    protected $signature = 'ark:bookstack:lockdown';

    protected $description = 'Remove orphan BookStack OIDC users and attribute catalog pages to the default ARK author';

    public function handle(BookStackLockdown $lockdown): int
    {
        if (! $lockdown->isConfigured()) {
            $this->warn('BookStack DB not configured (BOOKSTACK_DB_*). Skipping lockdown.');

            return self::SUCCESS;
        }

        $report = $lockdown->run();

        $this->info("Author BookStack user #{$report->authorBookStackUserId}");
        $this->line("Orphan OIDC users removed: {$report->orphansRemoved} (skipped: {$report->orphansSkipped})");
        $this->line("Catalog pages reattributed: {$report->pagesReattributed}");
        $this->line("Page revisions reattributed: {$report->revisionsReattributed}");
        $this->line('Import API token reassigned: '.($report->importTokenReassigned ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
