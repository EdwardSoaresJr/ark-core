<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Sell price and gross margin for parts cart lines before they are imported.
 *
 * Each row is priced exactly as PartsTechQuoteImporter will price it: a matrix-priced part line
 * on the chosen concern with the chosen matrix.
 */
final class PartsCartPricing
{
    public function __construct(
        private readonly RepairOrderLinePricing $pricing,
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * Without a concern the row is priced by its matrix alone, or the shop default matrix.
     *
     * @param  array{repair_order_concern_id?: int|null, part_cost: string|float|int, quantity?: string|float|int|null, pricing_matrix_key?: string|null}  $row
     * @return array<string, mixed>
     */
    public function price(RepairOrder $repairOrder, array $row): array
    {
        $settings = ShopSettings::current();
        $matrixKey = filled($row['pricing_matrix_key'] ?? null) ? (string) $row['pricing_matrix_key'] : null;

        if ($matrixKey !== null && $settings->partsMatrixByKey($matrixKey) === null) {
            return ['error' => 'This parts matrix is no longer available. Choose another.'];
        }

        $data = [
            'type' => RepairOrderLineType::Part->value,
            'part_cost' => (string) $row['part_cost'],
            'pricing_mode' => 'matrix',
            'repair_order_concern_id' => filled($row['repair_order_concern_id'] ?? null) ? (int) $row['repair_order_concern_id'] : null,
        ];

        if ($matrixKey !== null) {
            $data['pricing_matrix_key'] = $matrixKey;
            $data['pricing_matrix_explicit'] = true;
        }

        $preview = $this->pricing->previewFor($data, $repairOrder);
        $costCents = (int) $preview['part_cost_cents'];
        $sellCents = (int) $preview['unit_price_cents'];
        $quantity = filled($row['quantity'] ?? null) ? (string) $row['quantity'] : '1.00';

        return [
            'pricing_matrix_key' => $preview['pricing_matrix_key'],
            'pricing_matrix_name' => $preview['pricing_matrix_name'],
            'unit_cost_cents' => $costCents,
            'unit_sell_cents' => $sellCents,
            'unit_cost' => $this->money($costCents),
            'unit_sell' => $this->money($sellCents),
            'line_sell' => $this->money($this->calculator->lineTotalCents($quantity, $sellCents)),
            'margin_percentage' => $this->calculator->grossMarginPercentage($costCents, $sellCents, 1),
            'below_cost' => $sellCents < $costCents,
        ];
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
