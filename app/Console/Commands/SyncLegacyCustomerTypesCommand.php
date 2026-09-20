<?php

namespace App\Console\Commands;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLegacyCustomerTypesCommand extends Command
{
    protected $signature = 'ark:sync-legacy-customer-types
        {--dry-run : Show merged customer types without saving}';

    protected $description = 'Merge legacy ARK-SMS customer_types (shop fees, discounts, matrices) into shop settings.';

    public function handle(EstimateTotalsCalculator $totalsCalculator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $legacyTypes = DB::connection('arksms_legacy')
                ->table('customer_types')
                ->orderBy('sort_order')
                ->get();
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $settings = ShopSettings::current();
        $existingByName = collect($settings->customerTypeRows())
            ->keyBy(fn (array $row): string => mb_strtolower($row['name']));

        $merged = [];

        foreach ($legacyTypes as $legacy) {
            $name = trim((string) $legacy->name);

            if ($name === '') {
                continue;
            }

            $existing = $existingByName->get(mb_strtolower($name), []);
            $discountType = match ((string) ($legacy->parts_labor_discount_apply_to ?? 'both')) {
                'labor' => 'labor',
                'parts' => 'parts',
                'both' => 'both',
                default => 'none',
            };
            $discountAmount = (float) ($legacy->parts_labor_discount_percent ?? 0) > 0
                ? number_format((float) $legacy->parts_labor_discount_percent, 2, '.', '')
                : null;

            if ($discountType === 'none') {
                $discountAmount = null;
            }

            $merged[] = $settings->normalizeCustomerTypeRow([
                'name' => $name,
                'shop_fees_enabled' => (bool) ($legacy->show_shop_fees ?? true),
                'shop_fee_rate_override' => filled($legacy->shop_fee_rate_override ?? null)
                    ? (float) $legacy->shop_fee_rate_override
                    : null,
                'discount_type' => $existing['discount_type'] ?? $discountType,
                'discount_amount' => $existing['discount_amount'] ?? $discountAmount,
                'default_parts_matrix_key' => $existing['default_parts_matrix_key']
                    ?? (($legacy->no_parts_markup ?? false) ? 'warranty-no-markup' : null),
            ]);
        }

        foreach ($existingByName as $name => $existing) {
            if (! collect($merged)->contains(fn (array $row): bool => mb_strtolower($row['name']) === $name)) {
                $merged[] = $existing;
            }
        }

        if ($dryRun) {
            $this->line(json_encode($merged, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $settings->update(['customer_types' => $merged]);
        $recalculated = $totalsCalculator->recalculateLivingRepairOrders();

        $this->info('Synced '.count($merged).' customer types from legacy ARK-SMS.');
        $this->info("Recalculated {$recalculated} living repair orders.");

        return self::SUCCESS;
    }
}
