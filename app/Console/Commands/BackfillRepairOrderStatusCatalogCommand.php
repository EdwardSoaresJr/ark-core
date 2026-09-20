<?php

namespace App\Console\Commands;

use App\Ark\Import\LegacyArkSmsImportConfig;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillRepairOrderStatusCatalogCommand extends Command
{
    protected $signature = 'repair-orders:backfill-status-catalog
                            {--dry-run : Report changes without writing}';

    protected $description = 'Migrate repair order statuses to LNP catalog slugs and backfill close variants on closed ROs.';

    /** @var array<string, string> V2 compatibility slugs → LNP catalog slugs. */
    private const V2_STATUS_MAP = [
        'awaiting_approval' => 'waiting_approval',
        'ready_for_work' => 'approved',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('ro_statuses')) {
            $this->error('Status catalog is missing. Run migrations and RepairOrderStatusCatalogSeeder first.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('repair_orders', 'close_variant_key')) {
            $this->error('close_variant_key column is missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $legacyConnection = (string) config('legacy-arksms-import.connection', 'arksms_legacy');
        $legacyAvailable = $this->legacySourceAvailable($legacyConnection);
        $catalogSlugs = RepairOrderStatusDefinition::query()->pluck('slug')->all();
        $catalogSlugs = array_flip($catalogSlugs);

        if (! $legacyAvailable) {
            $this->warn('Legacy ARK-SMS source unavailable. Status migration will use V2 compatibility rules only.');
        }

        $statusUpdated = 0;
        $statusSkipped = 0;
        $variantUpdated = 0;
        $variantSkipped = 0;

        RepairOrder::query()
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use (
                &$statusUpdated,
                &$statusSkipped,
                &$variantUpdated,
                &$variantSkipped,
                $dryRun,
                $legacyConnection,
                $legacyAvailable,
                $catalogSlugs,
            ): void {
                foreach ($repairOrders as $repairOrder) {
                    $targetStatus = $this->resolveTargetStatus(
                        $repairOrder,
                        $legacyConnection,
                        $legacyAvailable,
                        $catalogSlugs,
                    );

                    if ($targetStatus !== null && $targetStatus !== $repairOrder->getRawOriginal('status')) {
                        if ($dryRun) {
                            $this->line("Would migrate RO #{$repairOrder->repair_order_id}: {$repairOrder->getRawOriginal('status')} → {$targetStatus}");
                        } else {
                            $repairOrder->forceFill(['status' => $targetStatus])->save();
                        }

                        $statusUpdated++;
                        $repairOrder = $repairOrder->fresh();
                    } elseif ($this->needsStatusMigration($repairOrder, $catalogSlugs)) {
                        $statusSkipped++;
                    }

                    if ($repairOrder->getRawOriginal('status') !== RepairOrderStatus::Closed->value
                        && ! $repairOrder->status->is(RepairOrderStatus::Closed)) {
                        continue;
                    }

                    if ($repairOrder->close_variant_key !== null) {
                        continue;
                    }

                    $variantKey = $this->resolveVariantKey($repairOrder, $legacyConnection, $legacyAvailable);

                    if ($variantKey === null) {
                        $variantSkipped++;
                        $this->line("Skipped close variant RO #{$repairOrder->repair_order_id} — no confident variant.");

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would set RO #{$repairOrder->repair_order_id} close_variant_key={$variantKey}");
                    } else {
                        $repairOrder->forceFill(['close_variant_key' => $variantKey])->save();
                    }

                    $variantUpdated++;
                }
            });

        $this->info(($dryRun ? 'Would migrate' : 'Migrated')." {$statusUpdated} repair order statuses. Skipped {$statusSkipped}.");
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$variantUpdated} close variants. Skipped {$variantSkipped}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $catalogSlugs
     */
    private function resolveTargetStatus(
        RepairOrder $repairOrder,
        string $legacyConnection,
        bool $legacyAvailable,
        array $catalogSlugs,
    ): ?string {
        $current = (string) $repairOrder->getRawOriginal('status');

        if ($current === RepairOrderStatus::Closed->value) {
            return null;
        }

        if ($legacyAvailable) {
            $legacySlug = $this->legacyStatusSlugForRepairOrder($repairOrder, $legacyConnection);

            if (
                $legacySlug !== null
                && isset($catalogSlugs[$legacySlug])
                && $legacySlug !== $current
                && $legacySlug !== RepairOrderStatus::Closed->value
            ) {
                return $legacySlug;
            }
        }

        if (isset(self::V2_STATUS_MAP[$current])) {
            return self::V2_STATUS_MAP[$current];
        }

        if ($current === RepairOrderStatus::ReadyPickup->value) {
            return $this->resolveReadyPickupTarget($repairOrder);
        }

        return null;
    }

    /**
     * @param  array<string, int>  $catalogSlugs
     */
    private function needsStatusMigration(RepairOrder $repairOrder, array $catalogSlugs): bool
    {
        $current = (string) $repairOrder->getRawOriginal('status');

        return ! isset($catalogSlugs[$current])
            || isset(self::V2_STATUS_MAP[$current])
            || $current === RepairOrderStatus::ReadyPickup->value;
    }

    private function resolveReadyPickupTarget(RepairOrder $repairOrder): string
    {
        $issuedInvoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder);

        return $issuedInvoice !== null
            ? RepairOrderStatus::Invoiced->value
            : RepairOrderStatus::Completed->value;
    }

    private function legacySourceAvailable(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('repair_order_statuses')
                && Schema::connection($connection)->hasColumn('repair_orders', 'status_id');
        } catch (Throwable) {
            return false;
        }
    }

    private function legacyVariantsAvailable(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('repair_order_status_variants')
                && Schema::connection($connection)->hasColumn('repair_orders', 'status_variant_id');
        } catch (Throwable) {
            return false;
        }
    }

    private function legacyStatusSlugForRepairOrder(RepairOrder $repairOrder, string $connection): ?string
    {
        $ordersTable = LegacyArkSmsImportConfig::table('repair_orders');
        $columns = LegacyArkSmsImportConfig::columns('repair_orders');
        $shopNumberColumn = $columns['shop_number'] ?? 'repair_order_id';

        $slug = DB::connection($connection)
            ->table($ordersTable.' as ro')
            ->join('repair_order_statuses as statuses', 'statuses.id', '=', 'ro.status_id')
            ->where('ro.'.$shopNumberColumn, $repairOrder->repair_order_id)
            ->value('statuses.slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    private function resolveVariantKey(RepairOrder $repairOrder, string $connection, bool $legacyAvailable): ?string
    {
        if ($legacyAvailable && $this->legacyVariantsAvailable($connection)) {
            $legacyVariant = $this->legacyVariantForRepairOrder($repairOrder, $connection);

            if ($legacyVariant !== null) {
                return $legacyVariant;
            }
        }

        return $this->heuristicVariantKey($repairOrder);
    }

    private function legacyVariantForRepairOrder(RepairOrder $repairOrder, string $connection): ?string
    {
        $ordersTable = LegacyArkSmsImportConfig::table('repair_orders');
        $columns = LegacyArkSmsImportConfig::columns('repair_orders');
        $shopNumberColumn = $columns['shop_number'] ?? 'repair_order_id';

        $legacyRow = DB::connection($connection)
            ->table($ordersTable)
            ->leftJoin('repair_order_status_variants as variants', 'variants.id', '=', "{$ordersTable}.status_variant_id")
            ->where("{$ordersTable}.{$shopNumberColumn}", $repairOrder->repair_order_id)
            ->first([
                "{$ordersTable}.status_variant_id",
                'variants.name as variant_name',
            ]);

        if ($legacyRow === null || $legacyRow->status_variant_id === null) {
            return null;
        }

        return match (strtolower((string) $legacyRow->variant_name)) {
            'paid' => 'paid',
            'lost' => 'lost',
            default => null,
        };
    }

    private function heuristicVariantKey(RepairOrder $repairOrder): ?string
    {
        if ($repairOrder->isPaid()) {
            return 'paid';
        }

        if ($repairOrder->balanceDue()->hasIssuedInvoice) {
            return 'paid';
        }

        return 'lost';
    }
}
