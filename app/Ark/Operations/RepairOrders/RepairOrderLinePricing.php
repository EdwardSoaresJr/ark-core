<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Labor\LaborAuthority;
use App\Ark\Operations\Settings\ShopSettings;

class RepairOrderLinePricing
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly LaborAuthority $laborAuthority,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     unit_price_cents: int,
     *     part_cost_cents: int|null,
     *     matrix_suggested_price_cents: int|null,
     *     pricing_mode: string|null,
     *     pricing_matrix_key: string|null,
     *     pricing_matrix_name: string|null,
     *     matrix_applied: bool,
     *     vendor_name: string|null,
     *     part_number: string|null,
     *     sourcing_notes: string|null,
     *     is_overridden: bool,
     *     quantity?: string,
     *     labor_category_key?: string|null,
     *     labor_category_name?: string|null,
     *     labor_entered_hours?: string|null,
     *     labor_adjustment?: string|null,
     *     labor_adjustment_factor?: string|null,
     *     labor_adjustment_reason?: string|null,
     *     labor_billed_hours?: string|null,
     *     labor_hours_overridden?: bool,
     *     labor_rate_cents?: int|null,
     * }
     */
    public function attributesFor(array $data, RepairOrder $repairOrder, ?RepairOrderLine $existingLine = null): array
    {
        $settings = ShopSettings::current();

        if ($data['type'] === RepairOrderLineType::Labor->value) {
            return $this->laborAttributesFor($data, $settings, $repairOrder, $existingLine);
        }

        if ($data['type'] === RepairOrderLineType::Sublet->value) {
            return $this->subletAttributesFor($data);
        }

        if ($data['type'] !== RepairOrderLineType::Part->value) {
            return [
                'quantity' => $data['quantity'] ?? '1.00',
                'unit_price_cents' => $this->nonPartUnitPriceCents($data, $settings),
                'part_cost_cents' => null,
                'matrix_suggested_price_cents' => null,
                'pricing_mode' => null,
                'pricing_matrix_key' => null,
                'pricing_matrix_name' => null,
                'matrix_applied' => false,
                'vendor_name' => null,
                'part_number' => null,
                'sourcing_notes' => null,
                'is_overridden' => false,
                'labor_category_key' => null,
                'labor_category_name' => null,
                'labor_entered_hours' => null,
                'labor_adjustment' => null,
                'labor_adjustment_factor' => null,
                'labor_adjustment_reason' => null,
                'labor_billed_hours' => null,
                'labor_hours_overridden' => false,
                'labor_override_reason' => null,
                'labor_minimum_applied' => false,
                'labor_rate_cents' => null,
            ];
        }

        $preview = $this->previewFor($data, $repairOrder);

        return [
            'quantity' => $data['quantity'] ?? '1.00',
            'unit_price_cents' => $preview['unit_price_cents'],
            'part_cost_cents' => $preview['part_cost_cents'],
            'matrix_suggested_price_cents' => $preview['matrix_suggested_price_cents'],
            'pricing_mode' => $preview['pricing_mode'],
            'pricing_matrix_key' => $preview['pricing_matrix_key'],
            'pricing_matrix_name' => $preview['pricing_matrix_name'],
            'matrix_applied' => $preview['matrix_applied'],
            'vendor_name' => filled($data['vendor_name'] ?? null) ? trim($data['vendor_name']) : null,
            'part_number' => filled($data['part_number'] ?? null) ? trim($data['part_number']) : null,
            'sourcing_notes' => filled($data['sourcing_notes'] ?? null) ? trim($data['sourcing_notes']) : null,
            'is_overridden' => $preview['is_overridden'],
            'labor_category_key' => null,
            'labor_category_name' => null,
            'labor_entered_hours' => null,
            'labor_adjustment' => null,
            'labor_adjustment_factor' => null,
            'labor_adjustment_reason' => null,
            'labor_billed_hours' => null,
            'labor_hours_overridden' => false,
            'labor_override_reason' => null,
            'labor_minimum_applied' => false,
            'labor_rate_cents' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function laborAttributesFor(
        array $data,
        ShopSettings $settings,
        RepairOrder $repairOrder,
        ?RepairOrderLine $existingLine = null,
    ): array {
        if (! filled($data['billing_posture'] ?? null)) {
            $repairOrder->loadMissing('concerns');
            $concern = $repairOrder->concerns->firstWhere('id', $data['repair_order_concern_id'] ?? null);
            $posture = $concern?->billing_posture;

            // Default posture means "shop default" — leave rate selection to category→policy map.
            if ($posture instanceof ConcernBillingPosture && $posture !== ConcernBillingPosture::Default) {
                $data['billing_posture'] = $posture->value;
            }
        }

        $resolved = $this->laborAuthority->resolveForLine($data, $settings, $existingLine);

        return [
            'quantity' => $resolved['quantity'],
            'unit_price_cents' => $resolved['unit_price_cents'],
            'part_cost_cents' => null,
            'matrix_suggested_price_cents' => null,
            'pricing_mode' => null,
            'pricing_matrix_key' => null,
            'pricing_matrix_name' => null,
            'matrix_applied' => false,
            'vendor_name' => null,
            'part_number' => null,
            'sourcing_notes' => null,
            'is_overridden' => false,
            'labor_category_key' => $resolved['labor_category_key'],
            'labor_category_name' => $resolved['labor_category_name'],
            'labor_entered_hours' => $resolved['labor_entered_hours'],
            'labor_adjustment' => $resolved['labor_adjustment'],
            'labor_adjustment_factor' => $resolved['labor_adjustment_factor'],
            'labor_adjustment_reason' => $resolved['labor_adjustment_reason'],
            'labor_billed_hours' => $resolved['labor_billed_hours'],
            'labor_hours_overridden' => $resolved['labor_hours_overridden'],
            'labor_override_reason' => $resolved['labor_override_reason'],
            'labor_minimum_applied' => $resolved['labor_minimum_applied'],
            'labor_rate_cents' => $resolved['labor_rate_cents'],
            'policy_resolved_labor_rate_cents' => $resolved['policy_resolved_labor_rate_cents'],
            'resolved_from_posture' => $resolved['resolved_from_posture'],
            'resolved_from_operation_class' => $resolved['resolved_from_operation_class'],
            'labor_policy_id' => $resolved['labor_policy_id'],
            'labor_policy_version' => $resolved['labor_policy_version'],
            'labor_rate_override_reason' => $resolved['labor_rate_override_reason'],
            'labor_rate_overridden_at' => $resolved['labor_rate_overridden_at'],
            'labor_rate_overridden_by_user_id' => $resolved['labor_rate_overridden_by_user_id'],
            'operation_id' => $resolved['operation_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     part_cost_cents: int|null,
     *     unit_price_cents: int,
     *     matrix_suggested_price_cents: int|null,
     *     pricing_mode: string,
     *     pricing_matrix_key: string,
     *     pricing_matrix_name: string,
     *     matrix_applied: bool,
     *     is_overridden: bool,
     *     suggested_sell: string|null,
     *     current_sell: string,
     *     margin_percentage: string|null,
     *     matrix_margin_percentage: string|null,
     *     markup_percentage: string|null,
     *     posture: string,
     *     guidance: string,
     * }
     */
    public function previewFor(array $data, RepairOrder $repairOrder): array
    {
        $settings = ShopSettings::current();
        $partCostCents = filled($data['part_cost'] ?? null)
            ? $this->calculator->unitPriceCents($data['part_cost'])
            : null;

        $repairOrder->loadMissing('customer', 'concerns');
        $concern = $repairOrder->concerns->firstWhere('id', $data['repair_order_concern_id'] ?? null);
        $posture = $concern?->billing_posture ?? ConcernBillingPosture::Default;
        $matrix = $this->explicitMatrixSelected($data)
            ? $settings->partsMatrixByKey($data['pricing_matrix_key'])
            : $posture->defaultPartsMatrix($settings);
        abort_if($matrix === null, 422, 'Selected parts matrix is not available.');

        $suggestion = $partCostCents === null
            ? null
            : $this->calculator->matrixSuggestionForCost($partCostCents, $settings, $matrix['key']);
        $suggestedPriceCents = $suggestion['suggested_price_cents'] ?? null;
        $pricingMode = $data['pricing_mode'] ?? ($posture->prefersManualPartPricing() ? 'manual' : 'matrix');
        $submittedUnitPriceCents = $this->submittedSellCents($data);

        if ($pricingMode === 'manual') {
            $unitPriceCents = $submittedUnitPriceCents ?? 0;
        } elseif ($pricingMode === 'matrix' && $suggestedPriceCents !== null) {
            $unitPriceCents = $this->resolveMatrixUnitPriceCents(
                $submittedUnitPriceCents,
                $suggestedPriceCents,
                $settings->default_labor_rate_cents,
                filter_var($data['unit_price_override'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        } else {
            $unitPriceCents = $submittedUnitPriceCents ?? $suggestedPriceCents ?? 0;
        }

        $matrixApplied = $pricingMode === 'matrix' && $suggestedPriceCents !== null && $unitPriceCents === $suggestedPriceCents;
        $isOverridden = $pricingMode === 'matrix' && $suggestedPriceCents !== null && $unitPriceCents !== $suggestedPriceCents;

        return [
            'part_cost_cents' => $partCostCents,
            'unit_price_cents' => $unitPriceCents,
            'matrix_suggested_price_cents' => $suggestedPriceCents,
            'pricing_mode' => $pricingMode,
            'pricing_matrix_key' => $matrix['key'],
            'pricing_matrix_name' => $matrix['name'],
            'matrix_applied' => $matrixApplied,
            'is_overridden' => $isOverridden,
            'suggested_sell' => $suggestedPriceCents === null ? null : $this->format($suggestedPriceCents),
            'sell_from_matrix' => $suggestedPriceCents === null
                ? null
                : number_format($suggestedPriceCents / 100, 2, '.', ''),
            'current_sell' => $this->format($unitPriceCents),
            'margin_percentage' => $this->calculator->grossMarginPercentage($partCostCents, $unitPriceCents),
            'matrix_margin_percentage' => $this->normalizePercent($suggestion['margin_percentage'] ?? null),
            'markup_percentage' => $this->normalizePercent($suggestion['markup_percentage'] ?? null),
            'posture' => $this->posture($pricingMode, $matrixApplied, $isOverridden),
            'guidance' => $this->guidance($matrix['name'], $suggestedPriceCents, $pricingMode, $matrixApplied, $isOverridden),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nonPartUnitPriceCents(array $data, ShopSettings $settings): int
    {
        if ($data['type'] === RepairOrderLineType::Labor->value && ! filled($data['unit_price'] ?? null)) {
            return $settings->default_labor_rate_cents;
        }

        return $this->calculator->unitPriceCents($data['unit_price'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function subletAttributesFor(array $data): array
    {
        $partCostCents = filled($data['part_cost'] ?? null)
            ? $this->calculator->unitPriceCents($data['part_cost'])
            : null;

        return [
            'quantity' => $data['quantity'] ?? '1.00',
            'unit_price_cents' => $this->calculator->unitPriceCents($data['unit_price'] ?? 0),
            'part_cost_cents' => $partCostCents,
            'matrix_suggested_price_cents' => null,
            'pricing_mode' => 'manual',
            'pricing_matrix_key' => null,
            'pricing_matrix_name' => null,
            'matrix_applied' => false,
            'vendor_name' => null,
            'part_number' => null,
            'sourcing_notes' => null,
            'is_overridden' => false,
            'labor_category_key' => null,
            'labor_category_name' => null,
            'labor_entered_hours' => null,
            'labor_adjustment' => null,
            'labor_adjustment_factor' => null,
            'labor_adjustment_reason' => null,
            'labor_billed_hours' => null,
            'labor_hours_overridden' => false,
            'labor_override_reason' => null,
            'labor_minimum_applied' => false,
            'labor_rate_cents' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     part_cost_cents: int|null,
     *     unit_price_cents: int,
     *     margin_percentage: string|null,
     *     markup_percentage: string|null,
     *     posture: string,
     *     guidance: string,
     * }
     */
    public function subletPreviewFor(array $data): array
    {
        $partCostCents = filled($data['part_cost'] ?? null)
            ? $this->calculator->unitPriceCents($data['part_cost'])
            : null;
        $unitPriceCents = filled($data['unit_price'] ?? null)
            ? $this->calculator->unitPriceCents($data['unit_price'])
            : 0;

        return [
            'part_cost_cents' => $partCostCents,
            'unit_price_cents' => $unitPriceCents,
            'margin_percentage' => $this->calculator->grossMarginPercentage($partCostCents, $unitPriceCents),
            'markup_percentage' => $this->calculator->costMarkupPercentage($partCostCents, $unitPriceCents),
            'posture' => 'manual price',
            'guidance' => $partCostCents === null
                ? 'Enter vendor cost to track margin on this sublet.'
                : ($unitPriceCents > 0
                    ? 'Sublet priced from cost and sell.'
                    : 'Enter sell price for this sublet.'),
        ];
    }

    private function posture(string $pricingMode, bool $matrixApplied, bool $isOverridden): string
    {
        if ($matrixApplied) {
            return 'matrix-derived';
        }

        if ($isOverridden) {
            return 'manually overridden';
        }

        return $pricingMode === 'manual' ? 'manual price' : 'matrix pending';
    }

    private function guidance(string $matrixName, ?int $suggestedPriceCents, string $pricingMode, bool $matrixApplied, bool $isOverridden): string
    {
        if ($suggestedPriceCents === null) {
            return 'Enter cost for server-priced matrix guidance.';
        }

        $suggested = $this->format($suggestedPriceCents);

        if ($matrixApplied) {
            return "{$matrixName} suggested {$suggested} accepted.";
        }

        if ($isOverridden) {
            return "{$matrixName} suggested {$suggested} overridden.";
        }

        return $pricingMode === 'manual'
            ? "{$matrixName} suggested {$suggested} available."
            : "{$matrixName} suggested {$suggested}.";
    }

    private function format(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    private function normalizePercent(string|float|int|null $percentage): ?string
    {
        if ($percentage === null || $percentage === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $percentage, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function explicitMatrixSelected(array $data): bool
    {
        return filter_var($data['pricing_matrix_explicit'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled($data['pricing_matrix_key'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function submittedSellCents(array $data): ?int
    {
        $raw = $data['unit_price'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $cents = max(0, $this->calculator->unitPriceCents($raw));
        $pricingMode = $data['pricing_mode'] ?? 'matrix';
        $unitPriceOverride = filter_var($data['unit_price_override'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($pricingMode === 'manual' || $unitPriceOverride) {
            return $cents;
        }

        return $cents > 0 ? $cents : null;
    }

    private function resolveMatrixUnitPriceCents(
        ?int $submittedUnitPriceCents,
        int $suggestedPriceCents,
        int $defaultLaborRateCents,
        bool $unitPriceOverride,
    ): int {
        if ($submittedUnitPriceCents === null) {
            return $suggestedPriceCents;
        }

        $staleLaborDefault = $submittedUnitPriceCents === $defaultLaborRateCents;

        if ($staleLaborDefault) {
            return $suggestedPriceCents;
        }

        if ($unitPriceOverride) {
            return $submittedUnitPriceCents;
        }

        if ($submittedUnitPriceCents !== $suggestedPriceCents) {
            return $submittedUnitPriceCents;
        }

        return $suggestedPriceCents;
    }
}
