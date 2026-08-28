<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Customer-facing estimate totals breakdown — gross labor and parts, discount, then fees and tax
 * so Labor + Parts − Discount + Fees + Tax equals Total.
 */
final class CustomerEstimateTotalsPresentation
{
    public static function customerTaxLabel(ShopSettings $settings): string
    {
        $label = $settings->taxLabel();

        if (! $settings->tax_enabled) {
            return $label;
        }

        $taxesParts = (bool) $settings->taxable_parts;
        $taxesLabor = (bool) $settings->taxable_labor;
        $taxesFees = (bool) $settings->taxable_shop_fees;

        if ($taxesParts && ! $taxesLabor && ! $taxesFees) {
            return $label.' (parts)';
        }

        if ($taxesLabor && ! $taxesParts && ! $taxesFees) {
            return $label.' (labor)';
        }

        if (! $taxesFees && ($taxesParts || $taxesLabor)) {
            return $label.' (excl. fees)';
        }

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromEstimateTotals(
        EstimateTotals $totals,
        ShopSettings $settings,
        ?string $standingDiscountLabel = null,
    ): array {
        return self::normalize(
            laborCents: $totals->grossLaborCents(),
            partsCents: $totals->grossPartsCents(),
            feesCents: $totals->feesCents(),
            standingDiscountCents: $totals->standingDiscountCents(),
            taxCents: $totals->taxCents(),
            totalCents: $totals->totalCents(),
            subtotalBeforeTaxCents: $totals->subtotalBeforeTaxCents(),
            taxLabel: self::customerTaxLabel($settings),
            standingDiscountLabel: $standingDiscountLabel,
            formatter: fn (int $cents): string => $totals->format($cents),
        );
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    public static function fromSnapshotTotals(array $totals, ?string $customerType = null): array
    {
        $standingDiscountCents = (int) ($totals['standing_discount_cents'] ?? 0);
        $laborCents = (int) ($totals['gross_labor_cents'] ?? 0);

        if ($laborCents === 0) {
            $laborCents = (int) ($totals['labor_cents'] ?? 0);

            if ($standingDiscountCents > 0 && ! array_key_exists('gross_labor_cents', $totals)) {
                $laborCents += $standingDiscountCents;
            }
        }

        $partsCents = (int) ($totals['gross_parts_cents'] ?? $totals['parts_cents'] ?? 0);
        $feesCents = (int) ($totals['fees_cents'] ?? 0);
        $taxCents = (int) ($totals['tax_cents'] ?? 0);
        $totalCents = (int) ($totals['total_cents'] ?? 0);

        $subtotalBeforeTaxCents = array_key_exists('subtotal_before_tax_cents', $totals)
            ? (int) $totals['subtotal_before_tax_cents']
            : max(0, $laborCents + $partsCents + $feesCents - $standingDiscountCents);

        $taxLabel = $totals['customer_tax_label']
            ?? $totals['tax_label']
            ?? 'Tax';

        $standingDiscountLabel = $totals['standing_discount_label']
            ?? StandingDiscountPresentation::label($customerType, $standingDiscountCents)
            ?? 'Discount';

        return self::normalize(
            laborCents: $laborCents,
            partsCents: $partsCents,
            feesCents: $feesCents,
            standingDiscountCents: $standingDiscountCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
            subtotalBeforeTaxCents: $subtotalBeforeTaxCents,
            taxLabel: (string) $taxLabel,
            standingDiscountLabel: $standingDiscountLabel,
            formatter: fn (int $cents): string => self::formatCents($cents),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalize(
        int $laborCents,
        int $partsCents,
        int $feesCents,
        int $standingDiscountCents,
        int $taxCents,
        int $totalCents,
        int $subtotalBeforeTaxCents,
        string $taxLabel,
        ?string $standingDiscountLabel,
        callable $formatter,
    ): array {
        return [
            'labor_cents' => $laborCents,
            'parts_cents' => $partsCents,
            'fees_cents' => $feesCents,
            'standing_discount_cents' => $standingDiscountCents,
            'standing_discount_label' => $standingDiscountLabel ?? 'Discount',
            'subtotal_before_tax_cents' => $subtotalBeforeTaxCents,
            'tax_cents' => $taxCents,
            'customer_tax_label' => $taxLabel,
            'total_cents' => $totalCents,
            'labor' => $formatter($laborCents),
            'parts' => $formatter($partsCents),
            'fees' => $formatter($feesCents),
            'standing_discount' => $formatter($standingDiscountCents),
            'subtotal_before_tax' => $formatter($subtotalBeforeTaxCents),
            'tax' => $formatter($taxCents),
            'total' => $formatter($totalCents),
            'show_subtotal_before_tax' => $taxCents > 0,
            'discount_note' => $standingDiscountCents > 0
                ? 'Discount applied at shop rates.'
                : null,
        ];
    }

    private static function formatCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }
}
