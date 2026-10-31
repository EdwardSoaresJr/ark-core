<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;

/**
 * An approval recorded before per-line amounts were kept says which lines were
 * approved but not at what price. The first time such a line is raised, its
 * amounts from just before the edit become its approved ceiling, so the increase
 * waits for a fresh authorization. The old approval record is not touched.
 */
final class LegacyApprovalCeiling
{
    public static function captureBeforeIncrease(RepairOrderLine $line): void
    {
        if (! $line->exists
            || $line->legacy_approved_amounts !== null
            || $line->type->isNote()
            || ! $line->isDirty(['quantity', 'unit_price_cents'])) {
            return;
        }

        $calculator = app(EstimateTotalsCalculator::class);
        $before = $calculator->lineTotalCents($line->getOriginal('quantity') ?? 0, (int) $line->getOriginal('unit_price_cents'));
        $after = $calculator->lineTotalCents($line->quantity ?? 0, (int) $line->unit_price_cents);

        if ($after <= $before) {
            return;
        }

        $concern = RepairOrderConcern::query()->find($line->getOriginal('repair_order_concern_id'));

        if ($concern?->disposition !== RepairOrderConcernDisposition::Approved) {
            return;
        }

        $latestScope = ApprovedWorkScope::query()
            ->where('repair_order_concern_id', $concern->id)
            ->latest('id')
            ->first();

        if ($latestScope?->lineAmounts() !== null) {
            return;
        }

        $line->legacy_approved_amounts = [
            'quantity' => (string) $line->getOriginal('quantity'),
            'unit_price_cents' => (int) $line->getOriginal('unit_price_cents'),
            'subtotal_cents' => (int) $line->getOriginal('subtotal_cents'),
            'standing_discount_cents' => (int) $line->getOriginal('standing_discount_cents'),
            'shop_fee_cents' => (int) $line->getOriginal('shop_fee_cents'),
            'tax_cents' => (int) $line->getOriginal('tax_cents'),
            'total_cents' => (int) $line->getOriginal('total_cents'),
        ];
    }
}
