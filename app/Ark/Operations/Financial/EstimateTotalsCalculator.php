<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Collection;

class EstimateTotalsCalculator
{
    /**
     * Persist authoritative line totals. Call from write paths after estimate mutations.
     */
    public function ensureFinancialTotalsAreCurrent(RepairOrder $repairOrder): void
    {
        if ($repairOrder->isTerminal()) {
            return;
        }

        $this->recalculateRepairOrder($repairOrder);
    }

    /**
     * @return int Number of repair orders recalculated.
     */
    public function recalculateLivingRepairOrders(): int
    {
        $recalculated = 0;

        RepairOrder::query()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->with('lines')
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use (&$recalculated): void {
                foreach ($repairOrders as $repairOrder) {
                    $this->recalculateRepairOrder($repairOrder);
                    $recalculated++;
                }
            });

        return $recalculated;
    }

    public function totalsFor(RepairOrder $repairOrder): EstimateTotals
    {
        $repairOrder->loadMissing(['lines.concern']);

        $settings = ShopSettings::current();
        $billableLines = $this->billableLines($repairOrder->lines);

        return $this->totalsForLines($repairOrder->lines, $billableLines, $settings);
    }

    /** Final invoice authority: approved disposition work only. */
    public function totalsForApprovedWork(RepairOrder $repairOrder): EstimateTotals
    {
        $repairOrder->loadMissing(['lines.concern']);
        $this->recalculateRepairOrder($repairOrder);
        $repairOrder->load(['lines.concern']);

        $settings = ShopSettings::current();
        $approvedLines = $this->approvedInvoiceableLines($repairOrder);

        return $this->totalsForLines($approvedLines, $approvedLines, $settings);
    }

    public function hasApprovedInvoiceableWork(RepairOrder $repairOrder): bool
    {
        $repairOrder->loadMissing(['lines.concern']);

        return $this->approvedInvoiceableLines($repairOrder)->isNotEmpty();
    }

    /**
     * Read-only approved-work totals for display projections (header / estimate
     * money). Mirrors totalsForApprovedWork() but GET-safe: it reads persisted
     * line totals and never recalculates or persists. Final invoice authority
     * stays with totalsForApprovedWork() (which recalculates before posting).
     */
    public function approvedTotalsForRead(RepairOrder $repairOrder): EstimateTotals
    {
        $repairOrder->loadMissing(['lines.concern']);

        $settings = ShopSettings::current();
        $approvedLines = $this->approvedInvoiceableLines($repairOrder);

        return $this->totalsForLines($approvedLines, $approvedLines, $settings);
    }

    /**
     * Read-only "waiting on a decision" totals — recommended work the customer
     * has not yet approved or declined. Deferred opportunity (ARO); GET-safe.
     * Distinct from the estimate total, which drops recommended work once any
     * work is approved (see RepairOrderConcernDisposition::countsTowardEstimateTotal).
     */
    public function recommendedTotalsForRead(RepairOrder $repairOrder): EstimateTotals
    {
        $repairOrder->loadMissing(['lines.concern']);

        $settings = ShopSettings::current();
        $recommendedLines = $repairOrder->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Recommended
                && ! $line->type->isNote())
            ->values();

        return $this->totalsForLines($recommendedLines, $recommendedLines, $settings);
    }

    /**
     * Read-only declined-work totals — customer declined lines. GET-safe.
     */
    public function declinedTotalsForRead(RepairOrder $repairOrder): EstimateTotals
    {
        $repairOrder->loadMissing(['lines.concern']);

        $settings = ShopSettings::current();
        $declinedLines = $repairOrder->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Declined
                && ! $line->type->isNote())
            ->values();

        return $this->totalsForLines($declinedLines, $declinedLines, $settings);
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function approvedInvoiceableLines(RepairOrder $repairOrder): Collection
    {
        return $repairOrder->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved
                && ! $line->type->isNote())
            ->values();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $allLines
     * @param  Collection<int, RepairOrderLine>  $billableLines
     */
    private function totalsForLines(Collection $allLines, Collection $billableLines, ShopSettings $settings): EstimateTotals
    {
        // Concern headers show deferred/recommended money for scan, but never draft —
        // draft is unfinished authoring and must not look like it belongs on the bill.
        $concernMoneyLines = $allLines->filter(
            fn (RepairOrderLine $line): bool => $line->concern?->disposition !== RepairOrderConcernDisposition::Draft,
        )->values();

        return new EstimateTotals(
            lines: $allLines,
            concernSubtotals: $this->concernSubtotals($concernMoneyLines),
            laborCents: $this->subtotalCents($billableLines, RepairOrderLineType::Labor)
                + $this->subtotalCents($billableLines, RepairOrderLineType::Sublet),
            partsCents: $this->subtotalCents($billableLines, RepairOrderLineType::Part),
            feesCents: $this->subtotalCents($billableLines, RepairOrderLineType::Fee) + (int) $billableLines->sum('shop_fee_cents'),
            taxCents: (int) $billableLines->sum('tax_cents'),
            taxableSellCents: $this->taxableSellCents($billableLines, $settings),
            allocatedShopFeesCents: (int) $billableLines->sum('shop_fee_cents'),
            standingDiscountCents: (int) $billableLines->sum('standing_discount_cents'),
            grossLaborCents: $this->grossSubtotalCentsForLaborRollup($billableLines),
            grossPartsCents: $this->grossSubtotalCents($billableLines, RepairOrderLineType::Part),
            packageCents: $this->subtotalCents($billableLines, RepairOrderLineType::Package),
            grossPackageCents: $this->grossSubtotalCents($billableLines, RepairOrderLineType::Package),
        );
    }

    public function lineTotalCents(string|float|int $quantity, int $unitPriceCents): int
    {
        return BigDecimal::of((string) $quantity)
            ->multipliedBy($unitPriceCents)
            ->toScale(0, RoundingMode::HALF_UP)
            ->toInt();
    }

    public function unitPriceCents(string|float|int $unitPrice): int
    {
        return Money::of((string) $unitPrice, 'USD', roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();
    }

    public function matrixSuggestedPriceCents(int $costCents, ?ShopSettings $settings = null, ?string $matrixKey = null): ?int
    {
        return $this->matrixSuggestionForCost($costCents, $settings, $matrixKey)['suggested_price_cents'] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function matrixSuggestedPriceCentsForRows(int $costCents, array $rows): ?int
    {
        if ($costCents < 0) {
            return null;
        }

        $costDollars = BigDecimal::of($costCents)->dividedBy(100, 2, RoundingMode::UNNECESSARY);

        foreach ($rows as $row) {
            $from = BigDecimal::of($row['min_cost']);
            $to = ($row['max_cost'] ?? null) === null || $row['max_cost'] === ''
                ? null
                : BigDecimal::of($row['max_cost']);

            if (! $costDollars->isGreaterThanOrEqualTo($from) || ($to !== null && $costDollars->isGreaterThan($to))) {
                continue;
            }

            return BigDecimal::of($costCents)
                ->multipliedBy(BigDecimal::one()->plus(BigDecimal::of($row['markup_percentage'])->dividedBy(100, 6, RoundingMode::HALF_UP)))
                ->toScale(0, RoundingMode::HALF_UP)
                ->toInt();
        }

        return null;
    }

    /**
     * @return array{
     *     matrix_key: string,
     *     matrix_name: string,
     *     markup_percentage: string|null,
     *     margin_percentage: string|null,
     *     suggested_price_cents: int,
     * }|null
     */
    public function matrixSuggestionForCost(int $costCents, ?ShopSettings $settings = null, ?string $matrixKey = null): ?array
    {
        if ($costCents < 0) {
            return null;
        }

        $settings ??= ShopSettings::current();
        $matrix = $settings->partsMatrixByKey($matrixKey);

        if (! $matrix) {
            return null;
        }

        foreach ($matrix['rows'] as $row) {
            $suggestedPriceCents = $this->matrixSuggestedPriceCentsForRows($costCents, [$row]);

            if ($suggestedPriceCents === null) {
                continue;
            }

            return [
                'matrix_key' => $matrix['key'],
                'matrix_name' => $matrix['name'],
                'markup_percentage' => $row['markup_percentage'] ?? null,
                'margin_percentage' => $row['margin_percentage'] ?? null,
                'suggested_price_cents' => $suggestedPriceCents,
            ];
        }

        return null;
    }

    public function grossMarginPercentage(?int $costCents, int $sellCents): ?string
    {
        if ($costCents === null || $sellCents <= 0) {
            return null;
        }

        return BigDecimal::of($sellCents - $costCents)
            ->multipliedBy(100)
            ->dividedBy($sellCents, 0, RoundingMode::HALF_UP)
            ->toScale(0)
            ->__toString();
    }

    public function costMarkupPercentage(?int $costCents, int $sellCents): ?string
    {
        if ($costCents === null || $costCents <= 0 || $sellCents <= 0) {
            return null;
        }

        return rtrim(rtrim(
            BigDecimal::of($sellCents - $costCents)
                ->multipliedBy(100)
                ->dividedBy($costCents, 2, RoundingMode::HALF_UP)
                ->toScale(2)
                ->__toString(),
            '0',
        ), '.');
    }

    public function recalculateRepairOrder(RepairOrder $repairOrder): void
    {
        $repairOrder->load(['lines', 'customer', 'concerns']);

        $settings = ShopSettings::current();

        if ($settings->shop_fee_enabled) {
            $repairOrder->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->isAllocatedShopFeeRollupLine())
                ->each
                ->delete();

            $repairOrder->load('lines');
        }

        $subtotals = $repairOrder->lines
            ->mapWithKeys(fn (RepairOrderLine $line): array => [
                $line->id => $this->subtotalForLine($line),
            ]);
        $standingDiscounts = $this->standingDiscountAllocations(
            $repairOrder,
            $repairOrder->lines,
            $subtotals,
            $settings,
        );
        $netSubtotals = $subtotals->map(function (int $subtotal, int $lineId) use ($standingDiscounts): int {
            return max(0, $subtotal - ($standingDiscounts[$lineId] ?? 0));
        });
        $shopFees = $this->shopFeeAllocations(
            $repairOrder,
            $repairOrder->lines,
            $netSubtotals,
            $settings,
        );

        foreach ($repairOrder->lines as $line) {
            $subtotalCents = $subtotals[$line->id];
            $standingDiscountCents = $standingDiscounts[$line->id] ?? 0;
            $netSubtotalCents = max(0, $subtotalCents - $standingDiscountCents);
            $shopFeeCents = $shopFees[$line->id] ?? 0;
            $taxCents = $this->taxCents($line, $netSubtotalCents, $shopFeeCents, $settings);

            $line->forceFill([
                'subtotal_cents' => $subtotalCents,
                'standing_discount_cents' => $standingDiscountCents,
                'tax_cents' => $taxCents,
                'shop_fee_cents' => $shopFeeCents,
                'total_cents' => $netSubtotalCents + $taxCents + $shopFeeCents,
            ])->save();
        }
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    public function billableEstimateLines(Collection $lines): Collection
    {
        return $this->billableLines($lines);
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function billableLines(Collection $lines): Collection
    {
        $hasApprovedWork = $lines->contains(
            fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved,
        );

        return $lines->filter(function (RepairOrderLine $line) use ($hasApprovedWork): bool {
            $disposition = $line->concern?->disposition;

            return $disposition === null || $disposition->countsTowardEstimateTotal($hasApprovedWork);
        })->values();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    public function estimateSubtotalCents(Collection $lines): int
    {
        return (int) $lines->sum('total_cents');
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    public function concernSubtotalCents(Collection $lines, int $concernId): int
    {
        return $this->estimateSubtotalCents(
            $lines->where('repair_order_concern_id', $concernId),
        );
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @return array<int, int>
     */
    private function concernSubtotals(Collection $lines): array
    {
        return $lines
            ->groupBy('repair_order_concern_id')
            ->map(fn (Collection $concernLines): int => $this->estimateSubtotalCents($concernLines))
            ->all();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function subtotalCents(Collection $lines, RepairOrderLineType $type): int
    {
        return (int) $lines
            ->where('type', $type)
            ->sum(fn (RepairOrderLine $line): int => $this->netSubtotalCents($line));
    }

    private function netSubtotalCents(RepairOrderLine $line): int
    {
        return max(0, $line->subtotal_cents - $line->standing_discount_cents);
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function grossSubtotalCentsForLaborRollup(Collection $lines): int
    {
        return (int) $lines
            ->filter(fn (RepairOrderLine $line): bool => $line->type->countsTowardLaborRollup())
            ->sum('subtotal_cents');
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function grossSubtotalCents(Collection $lines, RepairOrderLineType $type): int
    {
        return (int) $lines
            ->where('type', $type)
            ->sum('subtotal_cents');
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function taxableSellCents(Collection $lines, ShopSettings $settings): int
    {
        if (! $settings->tax_enabled) {
            return 0;
        }

        return (int) $lines->sum(function (RepairOrderLine $line) use ($settings): int {
            $netSubtotalCents = $this->netSubtotalCents($line);

            if ($line->type->countsTowardLaborRollup() && $settings->taxable_labor) {
                return $netSubtotalCents;
            }

            // Packages follow labor tax posture — service package, not parts.
            if ($line->type->isPackage() && $settings->taxable_labor) {
                return $netSubtotalCents;
            }

            if ($line->type === RepairOrderLineType::Part && $settings->taxable_parts) {
                return $netSubtotalCents;
            }

            return 0;
        });
    }

    private function subtotalForLine(RepairOrderLine $line): int
    {
        if ($line->type->isNote()) {
            return 0;
        }

        return $this->lineTotalCents($line->quantity, $line->unit_price_cents);
    }

    private function taxCents(RepairOrderLine $line, int $subtotalCents, int $shopFeeCents, ShopSettings $settings): int
    {
        if (! $settings->tax_enabled) {
            return 0;
        }

        if ($line->type->countsTowardLaborRollup() && ! $settings->taxable_labor) {
            return 0;
        }

        if ($line->type->isPackage() && ! $settings->taxable_labor) {
            return 0;
        }

        if ($line->type === RepairOrderLineType::Part && ! $settings->taxable_parts) {
            return 0;
        }

        if (! $line->type->isTaxableBaseCandidate()) {
            return 0;
        }

        $taxableBaseCents = $subtotalCents;

        if ($settings->taxable_shop_fees && $shopFeeCents > 0) {
            $taxableBaseCents += $shopFeeCents;
        }

        if ($taxableBaseCents === 0) {
            return 0;
        }

        return BigDecimal::of($taxableBaseCents)
            ->multipliedBy((string) $settings->default_tax_rate)
            ->dividedBy(100, 0, RoundingMode::HALF_UP)
            ->toInt();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @param  Collection<int, int>  $subtotals
     * @return array<int, int>
     */
    private function shopFeeAllocations(
        RepairOrder $repairOrder,
        Collection $lines,
        Collection $subtotals,
        ShopSettings $settings,
    ): array {
        if (! $settings->shop_fee_enabled) {
            return [];
        }

        $concernsById = $repairOrder->concerns->keyBy('id');

        $eligibleLines = $lines
            ->filter(fn (RepairOrderLine $line): bool => $line->type->isShopFeeEligible())
            ->filter(fn (RepairOrderLine $line): bool => ($subtotals[$line->id] ?? 0) > 0)
            ->filter(fn (RepairOrderLine $line): bool => $this->shopFeesApplyToLine(
                $line,
                $concernsById,
                $settings,
            ))
            ->values();

        if ($eligibleLines->isEmpty()) {
            return [];
        }

        $rawFees = $eligibleLines->mapWithKeys(function (RepairOrderLine $line) use ($subtotals, $concernsById, $settings): array {
            $rate = $this->shopFeeRateForLine($line, $concernsById, $settings);

            if (BigDecimal::of($rate)->isZero()) {
                return [$line->id => 0];
            }

            return [
                $line->id => BigDecimal::of($subtotals[$line->id])
                    ->multipliedBy($rate)
                    ->dividedBy(100, 0, RoundingMode::HALF_UP)
                    ->toInt(),
            ];
        });

        $totalFeeCents = (int) $rawFees->sum();

        if ($settings->shop_fee_cap_cents !== null && $totalFeeCents > $settings->shop_fee_cap_cents) {
            $totalFeeCents = $settings->shop_fee_cap_cents;
        }

        if ($totalFeeCents === 0) {
            return [];
        }

        $eligibleSubtotalCents = (int) $eligibleLines->sum(fn (RepairOrderLine $line): int => $subtotals[$line->id]);
        $allocated = [];
        $allocatedCents = 0;

        foreach ($eligibleLines as $index => $line) {
            if ($index === $eligibleLines->count() - 1) {
                $allocated[$line->id] = $totalFeeCents - $allocatedCents;

                continue;
            }

            $lineFeeCents = BigDecimal::of($totalFeeCents)
                ->multipliedBy($subtotals[$line->id])
                ->dividedBy($eligibleSubtotalCents, 0, RoundingMode::HALF_UP)
                ->toInt();

            $allocated[$line->id] = $lineFeeCents;
            $allocatedCents += $lineFeeCents;
        }

        return $allocated;
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @param  Collection<int, int>  $subtotals
     * @return array<int, int>
     */
    private function standingDiscountAllocations(
        RepairOrder $repairOrder,
        Collection $lines,
        Collection $subtotals,
        ShopSettings $settings,
    ): array {
        $policy = $settings->standingDiscountPolicy($repairOrder->customer?->customer_type);

        if ($policy === null) {
            return [];
        }

        $concernsById = $repairOrder->concerns->keyBy('id');
        $percent = BigDecimal::of($policy['percent']);

        $allocations = [];

        foreach ($lines as $line) {
            if (! $this->standingDiscountAppliesToLine($line, $policy, $concernsById)) {
                continue;
            }

            $grossSubtotalCents = $subtotals[$line->id] ?? 0;

            if ($grossSubtotalCents <= 0) {
                continue;
            }

            $allocations[$line->id] = BigDecimal::of($grossSubtotalCents)
                ->multipliedBy($percent)
                ->dividedBy(100, 0, RoundingMode::HALF_UP)
                ->toInt();
        }

        return $allocations;
    }

    /**
     * @param  array{type: string, percent: string}  $policy
     * @param  Collection<int, RepairOrderConcern>  $concernsById
     */
    private function standingDiscountAppliesToLine(
        RepairOrderLine $line,
        array $policy,
        Collection $concernsById,
    ): bool {
        if (! $line->type->isShopFeeEligible()) {
            return false;
        }

        if (! $this->billingPostureForLine($line, $concernsById)->acceptsStandingDiscount()) {
            return false;
        }

        return match ($policy['type']) {
            'labor' => $line->type->countsTowardLaborRollup() || $line->type->isPackage(),
            'parts' => $line->type === RepairOrderLineType::Part,
            'both' => true,
            default => false,
        };
    }

    /**
     * @param  Collection<int, RepairOrderConcern>  $concernsById
     */
    private function shopFeesApplyToLine(
        RepairOrderLine $line,
        Collection $concernsById,
        ShopSettings $settings,
    ): bool {
        $posture = $this->billingPostureForLine($line, $concernsById);

        return $posture->shopFeePolicy($settings)['enabled'];
    }

    /**
     * @param  Collection<int, RepairOrderConcern>  $concernsById
     */
    private function shopFeeRateForLine(
        RepairOrderLine $line,
        Collection $concernsById,
        ShopSettings $settings,
    ): string {
        $posture = $this->billingPostureForLine($line, $concernsById);
        $policy = $posture->shopFeePolicy($settings);

        if (! $policy['enabled']) {
            return '0';
        }

        return $policy['rate'] ?? (string) $settings->shop_fee_rate;
    }

    /**
     * @param  Collection<int, RepairOrderConcern>  $concernsById
     */
    private function billingPostureForLine(
        RepairOrderLine $line,
        Collection $concernsById,
    ): ConcernBillingPosture {
        return $concernsById->get($line->repair_order_concern_id)?->billing_posture
            ?? ConcernBillingPosture::Default;
    }
}
