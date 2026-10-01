<?php

namespace App\Console\Commands;

use App\Ark\Import\LegacyInvoicePaymentBackfill;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLegacyInvoicePaymentsCommand extends Command
{
    protected $signature = 'ark:backfill-legacy-invoice-payments
        {--shop-ro= : Shop repair order number (repair_orders.repair_order_id)}
        {--repair-order-id= : Internal repair order id}
        {--legacy-connection=arksms_legacy : Database connection for legacy ARK-SMS tenant data}
        {--legacy-host= : Override legacy database host}
        {--legacy-port=3306 : Override legacy database port}
        {--legacy-database= : Override legacy database name}
        {--legacy-username=root : Override legacy database username}
        {--legacy-password= : Override legacy database password}
        {--dry-run : Show the backfill plan without writing}
        {--force : Apply ledger entries}
        {--no-write-off : Do not write off any remaining balance}';

    protected $description = 'Backfill legacy ARK-SMS invoice payments and write off import remainder for imported repair orders.';

    public function handle(LegacyInvoicePaymentBackfill $backfill): int
    {
        $this->configureLegacyConnection();

        $connection = (string) $this->option('legacy-connection');

        try {
            DB::connection($connection)->getPdo();
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $repairOrder = $this->resolveRepairOrder();

        if ($repairOrder === null) {
            $this->error('Repair order not found.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('force') || $this->option('dry-run');

        if (! $dryRun && ! $this->option('force')) {
            $this->warn('Live backfill requires --force.');

            return self::FAILURE;
        }

        try {
            $result = $backfill->backfillRepairOrder(
                $repairOrder,
                $connection,
                $dryRun,
                ! $this->option('no-write-off'),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('RO #'.$repairOrder->repair_order_id.' (id '.$repairOrder->id.')');
        $this->line('Payments to record: '.$result['payments_recorded'].' (skipped existing: '.$result['payments_skipped'].')');
        $this->line('Write-off cents: '.$result['write_off_cents']);
        $this->line('Balance due cents: '.$result['balance_due_cents']);
        $this->line('Paid: '.($result['paid'] ? 'yes' : 'no'));

        if ($dryRun) {
            $this->info('Dry run only. Re-run with --force to apply.');
        } else {
            $this->info('Legacy invoice payments backfilled.');
        }

        return self::SUCCESS;
    }

    private function configureLegacyConnection(): void
    {
        $connection = (string) $this->option('legacy-connection');

        if ($this->option('legacy-database') === null) {
            return;
        }

        $base = config("database.connections.{$connection}", config('database.connections.arksms_legacy', []));

        config([
            "database.connections.{$connection}" => array_merge($base, array_filter([
                'driver' => 'mysql',
                'host' => $this->option('legacy-host') ?: ($base['host'] ?? '127.0.0.1'),
                'port' => $this->option('legacy-port') ?: ($base['port'] ?? '3306'),
                'database' => $this->option('legacy-database'),
                'username' => $this->option('legacy-username'),
                'password' => $this->option('legacy-password'),
                'charset' => $base['charset'] ?? 'utf8mb4',
                'collation' => $base['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ], fn (mixed $value): bool => $value !== null && $value !== '')),
        ]);
    }

    private function resolveRepairOrder(): ?RepairOrder
    {
        if ($this->option('repair-order-id')) {
            return RepairOrder::query()->find((int) $this->option('repair-order-id'));
        }

        if ($this->option('shop-ro')) {
            return RepairOrder::query()
                ->where('repair_order_id', (int) $this->option('shop-ro'))
                ->first();
        }

        $this->error('Provide --shop-ro or --repair-order-id.');

        return null;
    }
}
