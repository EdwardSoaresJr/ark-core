<?php

namespace App\Console\Commands;

use App\Ark\Operations\RepairOrders\UnresolvedAuthorizationReport;
use Illuminate\Console\Command;

class ReportAuthorizationIntegrityCommand extends Command
{
    protected $signature = 'ark:authorization-integrity';

    protected $description = 'List installed parts and completed labor with no approval coverage or an outstanding unapproved increase.';

    public function handle(UnresolvedAuthorizationReport $report): int
    {
        $rows = $report->rows();

        if ($rows->isEmpty()) {
            $this->info('No unresolved authorization.');

            return self::SUCCESS;
        }

        $this->table(
            ['RO', 'Concern', 'Line', 'Kind', 'Pending'],
            $rows->map(fn (array $row): array => [
                $row['repair_order_number'],
                $row['concern_id'],
                $row['line_id'],
                $row['kind'],
                number_format(((int) $row['pending_approval_cents']) / 100, 2, '.', ''),
            ])->all(),
        );

        $this->comment('Listed only. Nothing on these repair orders was changed.');

        return self::SUCCESS;
    }
}
