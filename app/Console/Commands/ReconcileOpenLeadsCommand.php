<?php

namespace App\Console\Commands;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReconcileOpenLeadsCommand extends Command
{
    protected $signature = 'ark:leads:reconcile-open
                            {--dry-run : Report matches without converting}';

    protected $description = 'One-time backfill — link open leads to existing repair orders by phone, vehicle, and concern.';

    public function handle(LeadConverter $converter): int
    {
        if (! Schema::hasTable('leads')) {
            $this->components->warn('leads table is not migrated yet.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $matches = [];

            Lead::query()
                ->open()
                ->whereNull('repair_order_id')
                ->orderBy('id')
                ->each(function (Lead $lead) use ($converter, &$matches): void {
                    $repairOrder = $converter->findRepairOrderForOpenLead($lead);

                    if ($repairOrder === null) {
                        return;
                    }

                    $matches[] = [
                        'lead_id' => $lead->id,
                        'shop_repair_order_id' => $repairOrder->repair_order_id,
                        'contact_name' => $lead->contact_name ?: 'Unknown',
                        'concern' => $lead->concern,
                    ];
                });

            if ($matches === []) {
                $this->components->info('No open leads matched an existing repair order.');

                return self::SUCCESS;
            }

            $this->components->info('Dry run — would convert '.count($matches).' lead(s):');
            $this->table(
                ['Lead ID', 'RO #', 'Contact', 'Concern'],
                collect($matches)->map(fn (array $row): array => [
                    $row['lead_id'],
                    $row['shop_repair_order_id'],
                    $row['contact_name'],
                    mb_strimwidth((string) $row['concern'], 0, 60, '…'),
                ])->all(),
            );

            return self::SUCCESS;
        }

        $linked = $converter->reconcileOpenLeads();

        if ($linked === []) {
            $this->components->info('No open leads matched an existing repair order.');

            return self::SUCCESS;
        }

        $this->components->info('Converted '.count($linked).' lead(s):');
        $this->table(
            ['Lead ID', 'RO #', 'Contact'],
            collect($linked)->map(fn (array $row): array => [
                $row['lead_id'],
                $row['shop_repair_order_id'],
                $row['contact_name'] ?: 'Unknown',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
