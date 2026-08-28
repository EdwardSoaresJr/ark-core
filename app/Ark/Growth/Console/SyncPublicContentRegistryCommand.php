<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Content\PublicContentRegistrySyncService;
use Illuminate\Console\Command;

final class SyncPublicContentRegistryCommand extends Command
{
    protected $signature = 'growth:sync-public-content';

    protected $description = '[Maintenance] Register known public marketing pages into the Growth content registry';

    public function handle(PublicContentRegistrySyncService $sync): int
    {
        $count = $sync->sync();

        $this->info("Synced {$count} public pages to growth_contents.");
        $this->line('Base URL: '.$sync->baseUrl());

        return self::SUCCESS;
    }
}
