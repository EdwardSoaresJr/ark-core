<?php

namespace App\Console\Commands;

use App\Ark\Operations\LaborGuides\Rte\RteCsvImporter;
use App\Ark\Operations\LaborGuides\Rte\RteImportManifest;
use App\Ark\Operations\LaborGuides\Rte\RteLaborLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RteImportCommand extends Command
{
    protected $signature = 'ark:rte-import
        {--path= : Directory containing rte_*.csv (default: base_path rte/)}
        {--truncate : Truncate RTE tables before import}
        {--table= : Import a single RTE table only}
        {--chunk=1000 : Insert batch size when LOAD DATA LOCAL INFILE is unavailable}
        {--verify : Run smoke-test join after import}
        {--force : Skip confirmation prompt}';

    protected $description = 'Import Real-Time Labor Guide CSV export into read-only rte_* reference tables.';

    public function handle(RteCsvImporter $importer, RteLaborLookup $lookup): int
    {
        if (! Schema::hasTable('rte_lab')) {
            $this->components->error('RTE tables are not migrated yet. Run: php artisan migrate');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: base_path('rte');
        $onlyTable = $this->option('table');

        if ($onlyTable !== null && ! array_key_exists($onlyTable, RteImportManifest::TABLES)) {
            $this->components->error("Unknown table [{$onlyTable}]. Valid: ".implode(', ', RteImportManifest::tableNames()));

            return self::FAILURE;
        }

        if (! is_dir($directory)) {
            $this->components->error("Export directory not found: {$directory}");

            return self::FAILURE;
        }

        if ($this->option('truncate') && ! $this->option('force')) {
            if (! $this->confirm('Truncate RTE reference tables before import?', false)) {
                $this->components->warn('Import cancelled.');

                return self::FAILURE;
            }
        }

        $this->components->info('Importing RTE labor guide CSVs from '.$directory);

        try {
            $counts = $importer->importDirectory(
                $directory,
                truncate: (bool) $this->option('truncate'),
                onlyTable: $onlyTable,
                chunkSize: max(100, (int) $this->option('chunk')),
            );
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($counts as $table => $imported) {
            $expected = RteImportManifest::TABLES[$table]['expected_rows'];
            $actual = (int) $lookup->rowCounts()[$table];
            $delta = $actual - $expected;
            $status = $delta === 0 ? 'ok' : ($delta > 0 ? "+{$delta}" : (string) $delta);

            $rows[] = [$table, number_format($imported), number_format($expected), number_format($actual), $status];
        }

        $this->table(['Table', 'Imported', 'Expected', 'Total now', 'Delta'], $rows);

        if ($this->option('verify')) {
            $this->newLine();

            return $this->runSmokeTest($lookup);
        }

        return self::SUCCESS;
    }

    private function runSmokeTest(RteLaborLookup $lookup): int
    {
        $result = $lookup->smokeTest();

        if ($result['passed']) {
            $this->components->info('Smoke test passed: '.$result['message']);

            return self::SUCCESS;
        }

        $this->components->error('Smoke test failed: '.$result['message']);

        return self::FAILURE;
    }
}
