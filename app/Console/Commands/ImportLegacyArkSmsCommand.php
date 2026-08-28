<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsImporter;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportOptions;
use App\Ark\Import\LegacyImportReport;
use Illuminate\Console\Command;

class ImportLegacyArkSmsCommand extends Command
{
    protected $signature = 'ark:import-legacy-arksms
        {--dry-run : Simulate import without writing to ARK SMS}
        {--force : Skip confirmation prompt and disable dry-run}
        {--limit= : Maximum legacy customers to process in this run}
        {--customer-id= : Import a single legacy customer id and related records}
        {--wipe-imported : Delete only rows previously imported (legacy id present)}
        {--resume : Continue from last checkpoint}
        {--audit-schema : Dump legacy table/column audit and exit}';

    protected $description = 'Import operational data from legacy ARK-SMS into ARK SMS (controlled, idempotent).';

    public function handle(): int
    {
        if ($this->option('audit-schema')) {
            return $this->auditSchema();
        }

        $dryRun = ! $this->option('force');

        if ($this->option('dry-run')) {
            $dryRun = true;
        }

        if (! $dryRun && ! $this->option('force')) {
            $this->warn('Live import requires --force.');

            return self::FAILURE;
        }

        $mapper = new LegacyArkSmsValueMapper;

        try {
            $reader = new DatabaseLegacyArkSmsReader;
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());
            $this->line('Configure ARKSMS_LEGACY_* in .env and verify the legacy schema with --audit-schema.');

            return self::FAILURE;
        }

        $importer = new LegacyArkSmsImporter($reader, $mapper);
        $report = new LegacyImportReport;

        $options = new LegacyImportOptions(
            dryRun: $dryRun,
            limit: $this->option('limit') !== null ? (int) $this->option('limit') : null,
            legacyCustomerId: $this->option('customer-id') !== null ? (int) $this->option('customer-id') : null,
            wipeImported: (bool) $this->option('wipe-imported'),
            resume: (bool) $this->option('resume'),
        );

        if ($options->wipeImported) {
            $importer->wipeImported($report, $options->dryRun);
        }

        if (! $dryRun) {
            $this->warn('Live import mutates ARK SMS. Ensure you have a current database backup.');
        }

        $importer->run($options, $report);
        $this->renderReport($report);

        return $report->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function auditSchema(): int
    {
        try {
            $reader = new DatabaseLegacyArkSmsReader;
            $importer = new LegacyArkSmsImporter($reader, new LegacyArkSmsValueMapper);
            $audit = $importer->auditSchema();
        } catch (\Throwable $exception) {
            $this->error('Legacy schema audit failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($audit as $entity => $details) {
            $exists = $details['exists'] ? 'yes' : 'NO';
            $this->line("<fg=cyan>{$entity}</> table={$details['configured_table']} exists={$exists}");

            if ($details['columns'] !== []) {
                $this->line('  columns: '.implode(', ', $details['columns']));
            }

            $this->line('  expected: '.implode(', ', $details['expected_columns']));
        }

        $this->newLine();
        $this->info('Update docs/imports/ark-sms-to-ark-v2.md if column names differ.');

        return self::SUCCESS;
    }

    private function renderReport(LegacyImportReport $report): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Dry run', $report->dryRun ? 'yes' : 'no'],
                ['Customers', $report->customersImported],
                ['Vehicles', $report->vehiclesImported],
                ['Repair orders', $report->repairOrdersImported],
                ['Concerns', $report->concernsImported],
                ['Lines', $report->linesImported],
                ['Invoices', $report->invoicesImported],
                ['Skipped', $report->skipped],
                ['Warnings', count($report->warnings)],
                ['Errors', count($report->errors)],
            ],
        );

        if ($report->unmappedStatuses !== []) {
            $this->warn('Unmapped statuses: '.implode(', ', array_unique($report->unmappedStatuses)));
        }

        if ($this->output->isVerbose() && $report->warnings !== []) {
            $this->line('Warnings:');
            foreach (array_slice($report->warnings, 0, 50) as $warning) {
                $this->line(" - {$warning}");
            }
        }

        $path = storage_path('app/'.config('legacy-arksms-import.report_path'));
        $this->info("Report written to {$path}");
    }
}
