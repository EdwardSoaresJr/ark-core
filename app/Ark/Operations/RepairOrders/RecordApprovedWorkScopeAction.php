<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Models\User;

final class RecordApprovedWorkScopeAction
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
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

        $lines = $concern->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() || $line->type->isLabor())
            ->values();

        return ApprovedWorkScope::query()->create([
            'repair_order_id' => $concern->repair_order_id,
            'repair_order_concern_id' => $concern->id,
            'line_ids' => $lines->map(fn (RepairOrderLine $line): int => (int) $line->id)->all(),
            'approved_lines' => $lines->map(fn (RepairOrderLine $line): array => [
                'id' => (int) $line->id,
                'description' => (string) $line->description,
                'customer_description' => (string) ($line->customer_description ?? ''),
                'subtotal_cents' => $this->authorization->approvalSubtotalCents($line),
                'quantity' => (string) $line->quantity,
                'unit_price_cents' => (int) $line->unit_price_cents,
                'standing_discount_cents' => (int) $line->standing_discount_cents,
                'shop_fee_cents' => (int) $line->shop_fee_cents,
                'tax_cents' => (int) $line->tax_cents,
                'total_cents' => (int) $line->total_cents,
            ])->all(),
            'recorded_by_user_id' => $actor?->id,
        ]);
    }
}
