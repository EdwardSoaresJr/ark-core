<?php

namespace App\Ark\Import;

final class LegacyImportReport
{
    public int $customersImported = 0;

    public int $vehiclesImported = 0;

    public int $repairOrdersImported = 0;

    public int $concernsImported = 0;

    public int $linesImported = 0;

    public int $invoicesImported = 0;

    public int $skipped = 0;

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $unmappedStatuses = [];

    /** @var list<string> */
    public array $duplicateMatches = [];

    public bool $dryRun = false;

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function skip(string $reason): void
    {
        $this->skipped++;
        $this->warnings[] = $reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'customers_imported' => $this->customersImported,
            'vehicles_imported' => $this->vehiclesImported,
            'repair_orders_imported' => $this->repairOrdersImported,
            'concerns_imported' => $this->concernsImported,
            'lines_imported' => $this->linesImported,
            'invoices_imported' => $this->invoicesImported,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'unmapped_statuses' => array_values(array_unique($this->unmappedStatuses)),
            'duplicate_matches' => $this->duplicateMatches,
            'finished_at' => now()->toIso8601String(),
        ];
    }
}
