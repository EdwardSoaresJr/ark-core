<?php

namespace App\Ark\Operations\ShopExcellence\PartsMatrixTune;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PartsMatrixHistoricalSampler
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * @return Collection<int, PartsMatrixLineSample>
     */
    public function sample(Carbon $from, Carbon $to, string $matrixKey): Collection
    {
        $settings = ShopSettings::current();
        $defaultKey = $settings->defaultPartsMatrix()['key'];

        return OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $from, $to))
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->whereNotNull('repair_order_lines.part_cost_cents')
            ->where('repair_order_lines.part_cost_cents', '>', 0)
            ->where('repair_order_lines.subtotal_cents', '>', 0)
            ->where(function (Builder $query) use ($matrixKey, $defaultKey): void {
                $query->where('repair_order_lines.pricing_matrix_key', $matrixKey);

                if ($matrixKey === $defaultKey) {
                    $query->orWhereNull('repair_order_lines.pricing_matrix_key');
                }
            })
            ->orderBy('repair_order_lines.id')
            ->get([
                'repair_order_lines.id',
                'repair_order_lines.part_cost_cents',
                'repair_order_lines.subtotal_cents',
                'repair_order_lines.pricing_matrix_key',
                'repair_order_lines.pricing_mode',
                'repair_order_lines.is_overridden',
                'repair_order_lines.matrix_suggested_price_cents',
                'repair_order_lines.quantity',
                'repair_order_lines.unit_price_cents',
            ])
            ->map(function ($line) use ($settings, $matrixKey): PartsMatrixLineSample {
                $costCents = (int) $line->part_cost_cents;
                $sellCents = (int) $line->subtotal_cents;
                $matrixSuggested = $line->matrix_suggested_price_cents
                    ?? $this->calculator->matrixSuggestedPriceCents($costCents, $settings, $matrixKey);

                $sellOverridden = (bool) $line->is_overridden
                    || ($line->pricing_mode ?? '') === 'manual'
                    || ($matrixSuggested !== null && abs($sellCents - (int) $matrixSuggested) > 1);

                return new PartsMatrixLineSample(
                    lineId: (int) $line->id,
                    costCents: $costCents,
                    sellCents: $sellCents,
                    pricingMatrixKey: $line->pricing_matrix_key,
                    pricingMode: (string) ($line->pricing_mode ?: 'matrix'),
                    sellOverridden: $sellOverridden,
                );
            });
    }
}
