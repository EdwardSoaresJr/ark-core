<?php

namespace App\Console\Commands;

use App\Ark\Operations\Learn\BookStack\ArkademyArticleHtmlExporter;
use App\Ark\Operations\Learn\BookStack\ArkademyBookStackImporter;
use App\Ark\Operations\Learn\BookStack\BookStackApiClient;
use Illuminate\Console\Command;

class ArkademyImportBookStackCommand extends Command
{
    protected $signature = 'ark:arkademy:import-bookstack
        {--dry-run : List catalog articles without calling BookStack}
        {--force : Import content to BookStack (required for live run)}';

    protected $description = 'Import ARKademy Blade catalog into BookStack and backfill arkademy_content_registry.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run') || ! $this->option('force');

        if (! $dryRun && ! $this->option('force')) {
            $this->warn('Live import requires --force.');

            return self::FAILURE;
        }

        $exporter = new ArkademyArticleHtmlExporter;

        if ($dryRun) {
            $this->info('Dry run — no BookStack writes.');
            $importer = new ArkademyBookStackImporter(
                client: new BookStackApiClient('', '', ''),
                exporter: $exporter,
            );
        } else {
            try {
                $client = BookStackApiClient::fromConfig();
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $importer = new ArkademyBookStackImporter(
                client: $client,
                exporter: $exporter,
            );
        }

        try {
            $report = $importer->import($dryRun, $this->output);
        } catch (\Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Shelf: {$report->shelfName}");
        $this->info("Books: {$report->bookCount}");
        $this->info('Articles: '.$report->articleCount.($dryRun ? ' (planned)' : " ({$report->importedPages} pages written)"));

        if (! $dryRun && $report->stalePagesRemoved > 0) {
            $this->info("Stale pages removed: {$report->stalePagesRemoved}");
        }

        if ($dryRun) {
            $this->line('Run with --force to push to BookStack.');
        }

        return self::SUCCESS;
    }
}
