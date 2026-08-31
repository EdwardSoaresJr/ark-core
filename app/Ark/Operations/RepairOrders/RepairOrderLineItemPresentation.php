<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Labor\LaborLinePresenter;
use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class RepairOrderLineItemPresentation
{
    public static function supplierLabel(RepairOrderLine $line): ?string
    {
        if (! $line->isPart()) {
            return null;
        }

        if ($line->part_source === PartLineSource::CustomerSupplied) {
            return 'Customer Supplied';
        }

        $vendor = trim((string) $line->vendor_name);

        return $vendor !== '' ? $vendor : null;
    }

    /**
     * Exception-only context for view mode — not already on chips or facts.
     *
     * @return list<string>
     */
    public static function viewContextLines(RepairOrderLine $line): array
    {
        $lines = [];

        if ($line->isPart()) {
            if (filled($line->sourcing_notes)) {
                $lines[] = trim((string) $line->sourcing_notes);
            }

            if (($line->part_warranty_impact ?? PartLineWarrantyImpact::None) !== PartLineWarrantyImpact::None) {
                $warrantyImpact = $line->part_warranty_impact ?? PartLineWarrantyImpact::None;

                $lines[] = match ($warrantyImpact) {
                    PartLineWarrantyImpact::CustomerSupplied => 'Customer supplied warranty impact',
                    PartLineWarrantyImpact::AftermarketRelated => 'Aftermarket warranty impact',
                    PartLineWarrantyImpact::Excluded => 'Warranty excluded',
                    default => 'Warranty: '.$warrantyImpact->label(),
                };
            }
        }

        if ($line->type->value === 'labor') {
            $authority = LaborLinePresenter::forLine($line);

            if ($authority !== null) {
                if (filled($authority['minimum_message'])) {
                    $lines[] = $authority['minimum_message'];
                } elseif ($authority['adjustment'] !== 'normal' || $authority['hours_overridden']) {
                    $lines[] = $authority['summary'];
                }
            }
        }

        return array_values(array_unique(array_filter($lines)));
    }

    /**
     * Full edit-mode context including part number and pricing notes.
     *
     * @return list<string>
     */
    public static function editContextLines(RepairOrderLine $line): array
    {
        $lines = self::viewContextLines($line);

        if ($line->isPart() && filled($line->part_number)) {
            $lines[] = 'Part # '.$line->part_number;
        }

        return array_values(array_unique($lines));
    }

    /**
     * @return array{label: string, variant: string}|null
     */
    public static function matrixPricingChip(RepairOrderLine $line): ?array
    {
        if (! $line->isPart()) {
            return null;
        }

        if ($line->matrix_suggested_price_cents !== null && $line->matrix_applied) {
            return ['label' => 'Matrix', 'variant' => 'matrix-accepted'];
        }

        if ($line->matrix_suggested_price_cents !== null && ($line->pricing_mode === 'matrix' || $line->is_overridden)) {
            return ['label' => 'Overridden', 'variant' => 'matrix-overridden'];
        }

        if ($line->pricing_mode === 'manual' && $line->part_cost_cents !== null) {
            return ['label' => 'Manual', 'variant' => 'matrix-manual'];
        }

        return null;
    }

    /**
     * Matrix pricing and markup — edit mode facts line.
     *
     * @return list<string>
     */
    public static function editPricingSegments(RepairOrderLine $line, EstimateTotals $totals): array
    {
        if (! $line->isPart()) {
            return [];
        }

        $segments = [];
        $matrixSegment = self::matrixPricingSegment($line, $totals);

        if ($matrixSegment !== null) {
            $segments[] = $matrixSegment;
        }

        if ($markupSegment = self::markupPricingSegment($line)) {
            $segments[] = $markupSegment;
        }

        return $segments;
    }

    public static function inlinePricingSegments(RepairOrderLine $line, EstimateTotals $totals): array
    {
        return self::editPricingSegments($line, $totals);
    }

    public static function matrixPricingSegment(RepairOrderLine $line, EstimateTotals $totals): ?string
    {
        if ($line->matrix_suggested_price_cents !== null) {
            $matrixName = $line->pricing_matrix_name ?: 'Matrix';

            if ($line->matrix_applied) {
                return "{$matrixName} accepted {$totals->format($line->matrix_suggested_price_cents)}";
            }

            if ($line->pricing_mode === 'matrix' || $line->is_overridden) {
                return "{$matrixName} overridden {$totals->format($line->unit_price_cents)}";
            }
        }

        if ($line->pricing_mode === 'manual' && $line->part_cost_cents !== null) {
            return 'Manual '.$totals->format($line->unit_price_cents);
        }

        return null;
    }

    public static function markupPricingSegment(RepairOrderLine $line): ?string
    {
        if (! $line->isPart()) {
            return null;
        }

        if ($markup = $line->matrixMarkupPercentage()) {
            return "Markup {$markup}%";
        }

        return null;
    }

    public static function procurementChipTone(PartProcurementState $state): string
    {
        return match ($state) {
            PartProcurementState::None => 'needs-ordered',
            PartProcurementState::Sourcing => 'sourcing',
            PartProcurementState::Ordered => 'ordered',
            PartProcurementState::Partial => 'partial',
            PartProcurementState::Received => 'received',
            PartProcurementState::Installed => 'installed',
            PartProcurementState::Backordered => 'backordered',
            PartProcurementState::AwaitingCustomer => 'waiting',
            PartProcurementState::Canceled => 'neutral',
        };
    }

    /**
     * @return array{
     *     percent: int,
     *     fill_percent: int,
     *     tone: string,
     *     label: string
     * }|null
     */
    public static function profitabilityMeter(RepairOrderLine $line): ?array
    {
        if (! $line->isPart()) {
            return null;
        }

        $rawPercent = $line->grossMarginPercentage();

        if ($rawPercent === null) {
            return null;
        }

        $percent = (int) $rawPercent;
        $target = ShopExcellenceTargets::current()['parts_margin_target_percent'];
        $fillPercent = max(0, min(100, $percent));

        if ($percent < 0) {
            return [
                'percent' => $percent,
                'fill_percent' => 0,
                'tone' => 'low',
                'label' => 'Low',
            ];
        }

        if ($percent >= $target) {
            $label = $percent >= $target + 5 ? 'Excellent' : 'Healthy';
            $tone = 'excellent';

            return [
                'percent' => $percent,
                'fill_percent' => $fillPercent,
                'tone' => $tone,
                'label' => $label,
            ];
        }

        if ($percent >= max(25, $target - 12)) {
            return [
                'percent' => $percent,
                'fill_percent' => $fillPercent,
                'tone' => 'thin',
                'label' => 'Thin',
            ];
        }

        return [
            'percent' => $percent,
            'fill_percent' => $fillPercent,
            'tone' => 'low',
            'label' => 'Low',
        ];
    }

    /**
     * @return array{title: string, items: list<array{label: string, detail: string}>, footer: string|null}|null
     */
    public static function profitabilityInspectCard(
        RepairOrderLine $line,
        EstimateTotals $totals,
        EstimateTotalsCalculator $calculator,
    ): ?array {
        if (! $line->isPart() || $line->part_cost_cents === null) {
            return null;
        }

        $sellCents = $line->unit_price_cents;
        $costCents = $line->part_cost_cents;
        $quantity = (float) $line->quantity;
        $grossProfitCents = (int) round($quantity * ($sellCents - $costCents));
        $marginPercent = $line->grossMarginPercentage();
        $markupPercent = $calculator->costMarkupPercentage($costCents, $sellCents);
        $target = ShopExcellenceTargets::current()['parts_margin_target_percent'];

        $items = [
            ['label' => 'Customer price', 'detail' => $totals->format($sellCents)],
            ['label' => 'Unit cost', 'detail' => $totals->format($costCents)],
            ['label' => 'Gross margin', 'detail' => $totals->format($grossProfitCents)],
        ];

        if ($marginPercent !== null) {
            $items[] = ['label' => 'Margin', 'detail' => $marginPercent.'%'];
        }

        if ($markupPercent !== null) {
            $items[] = ['label' => 'Markup', 'detail' => $markupPercent.'%'];
        }

        if ($line->shop_fee_cents > 0) {
            $items[] = ['label' => 'Shop fee', 'detail' => $totals->format($line->shop_fee_cents)];
        }

        if ($line->tax_cents > 0) {
            $items[] = ['label' => 'Tax', 'detail' => $totals->format($line->tax_cents)];
        }

        $footer = null;

        if ($marginPercent !== null) {
            $marginInt = (int) $marginPercent;

            if ($marginInt < $target) {
                $footer = 'Below target by '.($target - $marginInt).'%';
            } elseif ($marginInt >= $target + 5) {
                $footer = 'Excellent vs '.$target.'% target';
            } else {
                $footer = 'Healthy vs '.$target.'% target';
            }
        }

        return [
            'title' => 'Profitability',
            'items' => $items,
            'footer' => $footer,
        ];
    }

    /**
     * @return array{title: string, items: list<array{label: string, detail: string}>}|null
     */
    public static function supplierInspectCard(RepairOrderLine $line): ?array
    {
        if (! $line->isPart()) {
            return null;
        }

        $supplier = self::supplierLabel($line);

        if ($supplier === null) {
            return null;
        }

        $items = [
            ['label' => 'Supplier', 'detail' => $supplier],
            ['label' => 'Order status', 'detail' => $line->procurementStateLabel()],
        ];

        if ($line->dealer_quote_line_id) {
            if (! $line->relationLoaded('dealerQuoteLine')) {
                $line->load('dealerQuoteLine.quote');
            }

            $quote = $line->dealerQuoteLine?->quote;

            if ($quote !== null) {
                $items[] = [
                    'label' => 'Origin',
                    'detail' => 'Dealer Quote'.($quote->quote_number ? ' '.$quote->quote_number : ''),
                ];

                if (filled($quote->supplier_name)) {
                    $items[] = ['label' => 'Quote supplier', 'detail' => (string) $quote->supplier_name];
                }
            }
        }

        if ($line->part_source !== null) {
            $items[] = ['label' => 'Source', 'detail' => $line->part_source->label()];
        }

        if ($line->has_core) {
            $items[] = ['label' => 'Core', 'detail' => 'Yes'];
        }

        if ($line->save_old_part) {
            $items[] = ['label' => 'Save old part', 'detail' => 'Yes'];
        }

        return [
            'title' => 'Supplier',
            'items' => $items,
        ];
    }

    /**
     * @return array{title: string, items: list<array{label: string, detail: string}>}|null
     */
    public static function procurementInspectCard(RepairOrderLine $line, ?RepairOrder $repairOrder = null): ?array
    {
        if (! $line->isPart()) {
            return null;
        }

        return [
            'title' => 'Procurement',
            'items' => self::procurementInspectItems($line, $repairOrder),
        ];
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function procurementInspectItems(RepairOrderLine $line, ?RepairOrder $repairOrder = null): array
    {
        $items = [
            ['label' => 'Status', 'detail' => $line->procurementStateLabel()],
            ['label' => 'Next action', 'detail' => $line->procurementNextAction()],
        ];

        $vendor = trim((string) $line->vendor_name);

        if ($vendor !== '') {
            $items[] = ['label' => 'Vendor', 'detail' => $vendor];
        }

        if ($repairOrder !== null) {
            $launcher = app(PartsCatalogLauncher::class);

            if ($launcher->configured()) {
                $items[] = ['label' => 'PO', 'detail' => $launcher->poNumber($repairOrder)];
            }
        }

        if (self::procurementShowsStatusTimestamp($line->procurementState()) && $line->updated_at !== null) {
            $formatted = self::formatInspectTimestamp($line->updated_at);

            if ($formatted !== null) {
                $items[] = [
                    'label' => 'Status updated',
                    'detail' => $formatted,
                ];
            }
        }

        if (filled($line->part_number)) {
            $items[] = ['label' => 'Part #', 'detail' => $line->part_number];
        }

        if (filled($line->sourcing_notes)) {
            $items[] = ['label' => 'Sourcing', 'detail' => trim((string) $line->sourcing_notes)];
        }

        return $items;
    }

    private static function procurementShowsStatusTimestamp(PartProcurementState $state): bool
    {
        return match ($state) {
            PartProcurementState::None,
            PartProcurementState::Canceled => false,
            default => true,
        };
    }

    private static function formatInspectTimestamp(Carbon|CarbonInterface $instant): ?string
    {
        try {
            return ShopDisplayTimezone::format($instant);
        } catch (\Throwable) {
            return $instant->copy()->utc()->format('M j, Y g:i A');
        }
    }

    /**
     * @return array{title: string, items: list<array{label: string, detail: string}>}|null
     */
    public static function matrixInspectCard(RepairOrderLine $line, EstimateTotals $totals): ?array
    {
        if (! $line->isPart()) {
            return null;
        }

        $chip = self::matrixPricingChip($line);

        if ($chip === null && $line->matrix_suggested_price_cents === null) {
            return null;
        }

        $items = [];

        if ($line->pricing_matrix_name) {
            $items[] = ['label' => 'Matrix', 'detail' => $line->pricing_matrix_name];
        }

        if ($line->matrix_suggested_price_cents !== null) {
            $items[] = ['label' => 'Suggested', 'detail' => $totals->format($line->matrix_suggested_price_cents)];
        }

        $decision = match (true) {
            $line->matrix_applied => 'Accepted',
            $line->pricing_mode === 'manual' => 'Manual',
            $line->is_overridden => 'Overridden',
            default => 'Open',
        };

        $items[] = ['label' => 'Decision', 'detail' => $decision];
        $items[] = ['label' => 'Customer price', 'detail' => $totals->format($line->unit_price_cents)];

        if ($line->part_cost_cents !== null) {
            $items[] = ['label' => 'Unit cost', 'detail' => $totals->format($line->part_cost_cents)];
        }

        if ($markup = $line->matrixMarkupPercentage()) {
            $items[] = ['label' => 'Markup', 'detail' => $markup.'%'];
        }

        return [
            'title' => 'Matrix pricing',
            'items' => $items,
        ];
    }
}
