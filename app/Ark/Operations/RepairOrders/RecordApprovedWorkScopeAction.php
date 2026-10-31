<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;

final class RecordApprovedWorkScopeAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    public function execute(RepairOrderConcern $concern, ?User $actor = null): ?ApprovedWorkScope
    {
        if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
            return null;
        }

        // The recorded amounts must match what the customer was shown, so price the lines first.
        $this->totalsCalculator->recalculateRepairOrder($concern->repairOrder()->firstOrFail());
        $concern->load('lines');

        $lineIds = $concern->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() || $line->type->isLabor())
            ->map(fn (RepairOrderLine $line): int => (int) $line->id)
            ->values()
            ->all();

        $lineAmounts = $concern->lines
            ->reject(fn (RepairOrderLine $line): bool => $line->type->isNote())
            ->mapWithKeys(fn (RepairOrderLine $line): array => [
                (string) $line->id => [
                    'quantity' => (string) $line->quantity,
                    'unit_price_cents' => (int) $line->unit_price_cents,
                    'subtotal_cents' => (int) $line->subtotal_cents,
                    'standing_discount_cents' => (int) $line->standing_discount_cents,
                    'shop_fee_cents' => (int) $line->shop_fee_cents,
                    'tax_cents' => (int) $line->tax_cents,
                    'total_cents' => (int) $line->total_cents,
                ],
            ])
            ->all();

        return ApprovedWorkScope::query()->create([
            'repair_order_id' => $concern->repair_order_id,
            'repair_order_concern_id' => $concern->id,
            'line_ids' => $lineIds,
            'line_amounts' => $lineAmounts,
            'recorded_by_user_id' => $actor?->id,
        ]);
    }
}
