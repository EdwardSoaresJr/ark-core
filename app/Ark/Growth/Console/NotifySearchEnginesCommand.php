<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Integrations\SearchEngineNotificationService;
use Illuminate\Console\Command;

final class NotifySearchEnginesCommand extends Command
{
    protected $signature = 'growth:notify-search-engines';

    protected $description = '[Maintenance] Submit sitemap and recent URLs to IndexNow, Google Search Console, and Google Indexing API';

    public function handle(SearchEngineNotificationService $notifier): int
    {
        $results = $notifier->notifyAfterMaintenance();

        $this->info(json_encode($results, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
