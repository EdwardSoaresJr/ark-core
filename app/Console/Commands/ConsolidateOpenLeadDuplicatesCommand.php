<?php

namespace App\Console\Commands;

use App\Ark\Operations\Leads\LeadDuplicateConsolidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ConsolidateOpenLeadDuplicatesCommand extends Command
{
    protected $signature = 'ark:leads:consolidate-open-duplicates
                            {--phone= : Limit to one normalized phone number}
                            {--dry-run : Report duplicates without closing them}';

    protected $description = 'Close duplicate open leads on the same phone that belong to the same intake thread.';

    public function handle(LeadDuplicateConsolidator $consolidator): int
    {
        if (! Schema::hasTable('leads')) {
            $this->components->warn('leads table is not migrated yet.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $phone = filled($this->option('phone')) ? (string) $this->option('phone') : null;

        $closed = $consolidator->consolidateOpenDuplicates($phone, $dryRun);

        if ($closed === []) {
            $this->components->info('No duplicate open leads found.');

            return self::SUCCESS;
        }

        $this->components->info(($dryRun ? 'Would close ' : 'Closed ').count($closed).' duplicate lead(s):');
        $this->table(
            ['Duplicate ID', 'Kept ID', 'Phone'],
            collect($closed)->map(fn (array $row): array => [
                $row['duplicate_id'],
                $row['canonical_id'],
                $row['contact_phone'],
            ])->all(),
        );

        return self::SUCCESS;
    }
}
