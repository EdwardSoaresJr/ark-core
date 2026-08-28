<?php

namespace App\Console\Commands;

use App\Ark\Operations\Audit\OperationalAuditScenario;
use App\Ark\Operations\Audit\OperationalAuditSeeder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use Illuminate\Console\Command;

class ArkOperationalAuditCommand extends Command
{
    protected $signature = 'ark
        {--reset : Remove prior operational audit data before seeding}
        {--financial-only : Seed only financial workflow audit repair orders}';

    protected $description = 'Seed operational audit repair orders for workflow stress testing (not demo data).';

    public function handle(OperationalAuditSeeder $seeder, BalanceDueCalculator $balanceDue): int
    {
        if ($this->option('reset')) {
            $seeder->reset();
            $this->components->info('Removed prior operational audit data.');
        }

        $scenarios = $seeder->seed((bool) $this->option('financial-only'));

        $this->newLine();
        $this->components->info('Operational audit data ready. Spend 30 minutes in RO review — not PHPUnit.');
        $this->newLine();

        $rows = $scenarios->map(function (OperationalAuditScenario $scenario) use ($balanceDue): array {
            $repairOrder = $scenario->repairOrder->fresh();
            $balance = $balanceDue->forRepairOrder($repairOrder);
            $invoice = $balance->hasIssuedInvoice ? $balance->invoiceStatus->label() : 'Not issued';

            return [
                $scenario->title,
                '#'.$repairOrder->repair_order_id,
                $repairOrder->customer->name,
                $invoice,
                $balance->hasIssuedInvoice ? '$'.number_format($balance->balanceDueCents / 100, 2) : 'n/a',
                $scenario->purpose,
            ];
        })->all();

        $this->table(
            ['Scenario', 'RO', 'Customer', 'Invoice', 'Balance', 'Audit purpose'],
            $rows,
        );

        $this->newLine();
        $this->line('Review URLs:');

        foreach ($scenarios as $scenario) {
            $this->line("  {$scenario->title}: {$scenario->reviewUrl()}");

            foreach ($scenario->relatedRepairOrderIds as $relatedRepairOrderId) {
                $this->line("    Related RO #{$relatedRepairOrderId}");
            }

            $this->line("    Expect: {$scenario->expectations}");
        }

        $this->newLine();
        $this->comment('Marker: customers tagged '.OperationalAuditSeeder::MARKER);
        $this->comment('Re-run with --reset to replace audit data.');

        return self::SUCCESS;
    }
}
