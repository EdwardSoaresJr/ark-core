<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Integrations\SearchEngineNotificationService;
use Illuminate\Console\Command;

final class SubmitCommonProblemsForIndexingCommand extends Command
{
    protected $signature = 'growth:submit-common-problems-for-indexing';

    protected $description = '[Maintenance] Ping IndexNow and Google Indexing API for all Common Problems pages';

    public function handle(SearchEngineNotificationService $notifier): int
    {
        $paths = $notifier->commonProblemPaths();
        $this->info('Submitting '.count($paths).' Common Problems URL(s) for indexing…');

        $results = $notifier->notifyCommonProblems();

        $this->line(json_encode($results, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
