<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborPricingProfile;
use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\OperationAuthority\Operation;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderReadCache;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;
use App\Ark\Runtime\Authorization\PricingAuthority;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class LaborAuthority
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly LaborPolicyResolver $laborPolicyResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     quantity: string,
     *     unit_price_cents: int,
     *     labor_category_key: string,
     *     labor_category_name: string,
     *     labor_entered_hours: string,
     *     labor_adjustment: string,
     *     labor_adjustment_factor: string,
     *     labor_adjustment_reason: string|null,
     *     labor_billed_hours: string,
     *     labor_hours_overridden: bool,
     *     labor_override_reason: string|null,
     *     labor_minimum_applied: bool,
     *     labor_rate_cents: int,
     *     resolved_from_posture: string|null,
     *     resolved_from_operation_class: string|null,
     *     labor_policy_id: int|null,
     *     labor_policy_version: int|null,
     *     labor_rate_override_reason: string|null,
     * }
     */
    public function resolveForLine(array $data, ShopSettings $settings, ?RepairOrderLine $existingLine = null, ?RepairOrder $repairOrder = null): array
    {
        $categoryKey = filled($data['labor_category_key'] ?? null)
            ? (string) $data['labor_category_key']
            : $settings->defaultLaborCategory()['key'];
        $category = $settings->laborCategoryByKey($categoryKey)
            ?? $settings->defaultLaborCategory();

        abort_if($category === null, 422, 'Labor category is not available.');

        $allowsModifiers = $settings->laborCategoryAllowsModifiers($category['key']);
        $enteredHours = $this->hoursFromInput($data['labor_entered_hours'] ?? $data['quantity'] ?? '0');

        if ($allowsModifiers) {
            $adjustment = LaborAdjustment::tryFrom((string) ($data['labor_adjustment'] ?? LaborAdjustment::Normal->value))
                ?? LaborAdjustment::Normal;
            $factor = $this->resolveAdjustmentFactor($adjustment, $data['labor_adjustment_factor'] ?? null);
            $reason = $this->resolveAdjustmentReason($adjustment, $data);
        } else {
            $adjustment = LaborAdjustment::Normal;
            $factor = 1.0;
            $reason = null;
        }

        $adjustedHours = $enteredHours * $factor;
        $minimumHours = (float) $category['minimum_hours'];
        $afterMinimum = max($adjustedHours, $minimumHours);

        $roundingRule = LaborRoundingRule::tryFrom((string) ($category['rounding_rule']))
            ?? LaborRoundingRule::Quarter;
        $calculatedBilledHours = $roundingRule->roundHours($afterMinimum);

        $minimumApplied = $adjustedHours < $minimumHours && $minimumHours > 0;

        if ($allowsModifiers) {
            $hoursOverridden = filter_var($data['labor_hours_overridden'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $submittedQuantity = $this->hoursFromInput($data['quantity'] ?? (string) $calculatedBilledHours);
            $billedHours = $hoursOverridden ? $submittedQuantity : $calculatedBilledHours;
            $overrideReason = $this->resolveOverrideReason(
                $hoursOverridden,
                $calculatedBilledHours,
                $billedHours,
                $data,
            );
        } else {
            $hoursOverridden = false;
            $billedHours = $calculatedBilledHours;
            $overrideReason = null;
        }

        $operation = Operation::forLine(
            filled($data['operation_id'] ?? null)
                ? (int) $data['operation_id']
                : ($existingLine?->operation_id !== null ? (int) $existingLine->operation_id : null),
            $category['key'],
        );

        $policyResolution = $this->resolvePolicyRate($data, $category['key'], $settings, $operation, $repairOrder);
        $policyRateCents = $policyResolution['rate_cents'];
        $snapshot = $policyResolution['snapshot'];

        $overrideRequested = filter_var(
            $data['labor_rate_overridden'] ?? $data['unit_price_override'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $rateOverridden = $allowsModifiers && $overrideRequested;
        $explicitReprice = filter_var($data['reprice_labor'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Category change is an intentional pricing context change (e.g. mechanical → courtesy → $0).
        // Hours/description edits alone preserve the immutable rate snapshot.
        $categoryUnchanged = $existingLine !== null
            && (string) $existingLine->labor_category_key === $category['key'];

        $preserveSnapshot = $existingLine !== null
            && $existingLine->labor_rate_cents !== null
            && $categoryUnchanged
            && ! $explicitReprice
            && ! $rateOverridden;

        $rateOverrideReason = null;
        $rateOverriddenAt = null;
        $rateOverriddenByUserId = null;

        if ($preserveSnapshot) {
            $rateCents = (int) $existingLine->labor_rate_cents;
            $policyRateCents = $existingLine->policy_resolved_labor_rate_cents !== null
                ? (int) $existingLine->policy_resolved_labor_rate_cents
                : $rateCents;
            $snapshot = [
                'resolved_from_posture' => $existingLine->resolved_from_posture,
                'resolved_from_operation_class' => $existingLine->resolved_from_operation_class,
                'labor_policy_id' => $existingLine->labor_policy_id,
                'labor_policy_version' => $existingLine->labor_policy_version,
            ];
            $rateOverrideReason = $existingLine->labor_rate_override_reason;
            $rateOverriddenAt = $existingLine->labor_rate_overridden_at;
            $rateOverriddenByUserId = $existingLine->labor_rate_overridden_by_user_id;
            // Keep pricing-time Operation with the immutable snapshot when category is unchanged.
            $operationId = $existingLine->operation_id !== null
                ? (int) $existingLine->operation_id
                : $operation->id;
        } else {
            $rateCents = $policyRateCents;
            $operationId = $operation->id;

            if ($rateOverridden && filled($data['unit_price'] ?? null)) {
                $submittedRateCents = $this->calculator->unitPriceCents($data['unit_price']);

                if ($submittedRateCents !== $policyRateCents) {
                    if (! PricingAuthority::allows(Auth::user())) {
                        throw ValidationException::withMessages([
                            'unit_price' => 'Manual pricing overrides require pricing authority.',
                        ]);
                    }

                    $rateCents = $submittedRateCents;
                    $rateOverrideReason = $this->resolveRateOverrideReason($data);
                    $rateOverriddenAt = now();
                    $rateOverriddenByUserId = Auth::id();
                }
            }
        }

        if ($overrideRequested
            && ! $allowsModifiers
            && filled($data['unit_price'] ?? null)
            && $this->calculator->unitPriceCents($data['unit_price']) !== $rateCents) {
            throw ValidationException::withMessages([
                'unit_price' => sprintf(
                    '%s labor is billed at its policy rate ($%s/hr) and its rate cannot be overridden. Change the concern billing or the labor category instead.',
                    $category['name'],
                    number_format($rateCents / 100, 2),
                ),
            ]);
        }

        return [
            'quantity' => $this->formatHours($billedHours),
            'unit_price_cents' => $rateCents,
            'labor_category_key' => $category['key'],
            'labor_category_name' => $category['name'],
            'labor_entered_hours' => $this->formatHours($enteredHours),
            'labor_adjustment' => $adjustment->value,
            'labor_adjustment_factor' => $this->formatFactor($factor),
            'labor_adjustment_reason' => $reason,
            'labor_billed_hours' => $this->formatHours($calculatedBilledHours),
            'labor_hours_overridden' => $hoursOverridden,
            'labor_override_reason' => $overrideReason,
            'labor_minimum_applied' => $minimumApplied,
            'labor_rate_cents' => $rateCents,
            'policy_resolved_labor_rate_cents' => $policyRateCents,
            'resolved_from_posture' => $snapshot['resolved_from_posture'],
            'resolved_from_operation_class' => $snapshot['resolved_from_operation_class'],
            'labor_policy_id' => $snapshot['labor_policy_id'],
            'labor_policy_version' => $snapshot['labor_policy_version'],
            'labor_rate_override_reason' => $rateOverrideReason,
            'labor_rate_overridden_at' => $rateOverriddenAt,
            'labor_rate_overridden_by_user_id' => $rateOverriddenByUserId,
            'operation_id' => $operationId,
        ];
    }

    /**
     * Re-prices a policy-priced line after its concern's billing changed. In the same category only
     * the rate and its policy snapshot change; moving to another program category recomputes billed
     * hours from the entered hours under that category's minimum and rounding.
     *
     * @return array<string, mixed>
     */
    public function repriceForBillingChange(
        RepairOrderLine $line,
        ConcernBillingPosture|string $posture,
        string $categoryKey,
        ShopSettings $settings,
    ): array {
        if ($categoryKey !== (string) $line->labor_category_key) {
            return $this->resolveForLine([
                'labor_category_key' => $categoryKey,
                'labor_entered_hours' => (string) ($line->labor_entered_hours ?? $line->quantity),
                'labor_adjustment' => LaborAdjustment::Normal->value,
                ...self::billingPostureInput($posture),
            ], $settings);
        }

        $operation = Operation::forLine(
            $line->operation_id !== null ? (int) $line->operation_id : null,
            $categoryKey,
        );
        $resolution = $this->resolvePolicyRate(self::billingPostureInput($posture), $categoryKey, $settings, $operation);

        return [
            'quantity' => (string) $line->quantity,
            'unit_price_cents' => $resolution['rate_cents'],
            'labor_rate_cents' => $resolution['rate_cents'],
            'policy_resolved_labor_rate_cents' => $resolution['rate_cents'],
            ...$resolution['snapshot'],
            'labor_rate_override_reason' => null,
            'labor_rate_overridden_at' => null,
            'labor_rate_overridden_by_user_id' => null,
            'operation_id' => $operation->id,
        ];
    }

    /**
     * The hourly rate a new labor line in this category is saved at on a concern with this billing.
     */
    public function policyRateCentsFor(string $categoryKey, ConcernBillingPosture|string $posture, ShopSettings $settings): int
    {
        $postureKey = $posture instanceof ConcernBillingPosture ? $posture->value : (string) $posture;

        return app(RepairOrderReadCache::class)->remember(
            "labor-policy-rate:{$postureKey}:{$categoryKey}",
            function () use ($categoryKey, $posture, $settings): int {
                try {
                    $operation = $this->activeOperationsByCode()->get($categoryKey)
                        ?? Operation::forLine(null, $categoryKey);

                    return $this->resolvePolicyRate(
                        self::billingPostureInput($posture),
                        $categoryKey,
                        $settings,
                        $operation,
                    )['rate_cents'];
                } catch (RuntimeException) {
                    return $this->legacyCategoryRateCents($categoryKey, $settings);
                }
            },
        );
    }

    /**
     * @return Collection<string, Operation>
     */
    private function activeOperationsByCode(): Collection
    {
        return app(RepairOrderReadCache::class)->remember('active-operations', function () {
            $classes = $this->laborPolicyResolver->operationClasses()->keyBy('id');

            return Operation::query()
                ->where('is_active', true)
                ->get()
                ->each(fn (Operation $operation) => $operation->setRelation('operationClass', $classes->get($operation->operation_class_id)))
                ->keyBy('code');
        });
    }

    /**
     * Shop default billing leaves the posture to the labor category, as line pricing does.
     *
     * @return array{billing_posture?: string}
     */
    public static function billingPostureInput(ConcernBillingPosture|string|null $posture): array
    {
        if (is_string($posture) && $posture !== '' && $posture !== ConcernBillingPosture::Default->value) {
            return ['billing_posture' => $posture];
        }

        return $posture instanceof ConcernBillingPosture && $posture !== ConcernBillingPosture::Default
            ? ['billing_posture' => $posture->value]
            : [];
    }

    /**
     * @return array{billing_posture?: string}
     */
    public static function billingPostureInputFromConcern(?RepairOrderConcern $concern): array
    {
        if ($concern === null) {
            return [];
        }

        return self::billingPostureInput($concern->billingClassKey());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRateOverrideReason(array $data): string
    {
        $reasonKey = trim((string) ($data['labor_rate_override_reason'] ?? ''));

        if ($reasonKey === '') {
            throw ValidationException::withMessages([
                'labor_rate_override_reason' => 'Select a reason when overriding the labor policy rate.',
            ]);
        }

        $reason = LaborRateOverrideReason::tryFrom($reasonKey);

        if (! $reason instanceof LaborRateOverrideReason) {
            throw ValidationException::withMessages([
                'labor_rate_override_reason' => 'Select a valid labor rate override reason.',
            ]);
        }

        return $reason->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     rate_cents: int,
     *     snapshot: array{
     *         resolved_from_posture: string|null,
     *         resolved_from_operation_class: string|null,
     *         labor_policy_id: int|null,
     *         labor_policy_version: int|null,
     *     }
     * }
     */
    private function resolvePolicyRate(
        array $data,
        string $categoryKey,
        ShopSettings $settings,
        Operation $operation,
        ?RepairOrder $repairOrder = null,
    ): array {
        // Courtesy labor category still uses shop category rate until posture is set (pricing concern, not Operation).
        if ($categoryKey === 'courtesy' && ! filled($data['billing_posture'] ?? null)) {
            return [
                'rate_cents' => $this->legacyCategoryRateCents($categoryKey, $settings),
                'snapshot' => [
                    'resolved_from_posture' => null,
                    'resolved_from_operation_class' => null,
                    'labor_policy_id' => null,
                    'labor_policy_version' => null,
                ],
            ];
        }

        $operationClassKey = filled($data['operation_class_key'] ?? null)
            ? (string) $data['operation_class_key']
            : $operation->operationClassKey();

        $posture = $this->resolveBillingPosture($data, $categoryKey);

        // Forward-only: stamped profile rates win for ROs created after matrix activation.
        if ($repairOrder !== null) {
            $profile = LaborPricingProfile::tryFromStored(
                is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
            );

            if ($profile?->hasFrozenRates()) {
                $frozen = $profile->rateCentsForOperationClass($operationClassKey, $posture)
                    ?? $profile->rateCentsFor($categoryKey, $posture);

                if ($frozen !== null) {
                    return [
                        'rate_cents' => $frozen,
                        'snapshot' => [
                            'resolved_from_posture' => $posture instanceof ConcernBillingPosture
                                ? $posture->value
                                : (string) $posture,
                            'resolved_from_operation_class' => $operationClassKey,
                            'labor_policy_id' => null,
                            'labor_policy_version' => $profile->version,
                        ],
                    ];
                }
            }
        }

        // Legacy null-profile ROs: resolve as-of open date so later policy versions do not rewrite new lines.
        $asOf = null;
        if ($repairOrder?->opened_at !== null) {
            $asOf = Carbon::parse($repairOrder->opened_at);
        }

        $resolved = $this->laborPolicyResolver->resolve($posture, $operationClassKey, $asOf);

        return [
            'rate_cents' => $resolved->hourlyRateCents,
            'snapshot' => $resolved->toLineSnapshot(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * Target chain: Billing Class → Billing Posture → Pricing.
     *
     * TODO(migration): Delete category→posture inference once every labor resolve
     * receives an explicit concern billing posture. Transitional only - do not freeze.
     */
    private function resolveBillingPosture(array $data, string $categoryKey): ConcernBillingPosture|string
    {
        if (filled($data['billing_posture'] ?? null)) {
            $raw = (string) $data['billing_posture'];
            $explicit = ConcernBillingPosture::tryFrom($raw);
            if ($explicit instanceof ConcernBillingPosture) {
                return $explicit;
            }

            // Shop-defined custom billing class key.
            if ($raw !== '' && ShopSettings::current()->customLaborBillingClass($raw) !== null) {
                return $raw;
            }
        }

        return match ($categoryKey) {
            ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY => ConcernBillingPosture::RepairPal,
            ShopSettings::WARRANTY_OTHER_LABOR_CATEGORY_KEY, 'warranty' => ConcernBillingPosture::WarrantyOther,
            ShopSettings::COMEBACK_LABOR_CATEGORY_KEY => ConcernBillingPosture::Comeback,
            'internal' => ConcernBillingPosture::Internal,
            default => ConcernBillingPosture::CustomerPay,
        };
    }

    private function legacyCategoryRateCents(string $categoryKey, ShopSettings $settings): int
    {
        $category = $settings->laborCategoryByKey($categoryKey) ?? $settings->defaultLaborCategory();

        if ($category === null) {
            return (int) $settings->default_labor_rate_cents;
        }

        return $category['key'] === ShopSettings::DEFAULT_LABOR_CATEGORY_KEY
            ? $settings->default_labor_rate_cents
            : (int) $category['rate_cents'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOverrideReason(
        bool $hoursOverridden,
        float $calculatedBilledHours,
        float $billedHours,
        array $data,
    ): ?string {
        if (! $hoursOverridden || abs($billedHours - $calculatedBilledHours) < 0.005) {
            return null;
        }

        $reason = trim((string) ($data['labor_override_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'labor_override_reason' => 'Explain why final labor hours differ from the calculated amount.',
            ]);
        }

        return $reason;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAdjustmentReason(LaborAdjustment $adjustment, array $data): ?string
    {
        if (! $adjustment->requiresReason()) {
            return null;
        }

        $reasonKey = trim((string) ($data['labor_adjustment_reason'] ?? ''));

        if ($reasonKey === '') {
            throw ValidationException::withMessages([
                'labor_adjustment_reason' => 'Select a reason when labor difficulty is adjusted.',
            ]);
        }

        $reason = LaborAdjustmentReason::tryFrom($reasonKey);

        if (! $reason instanceof LaborAdjustmentReason) {
            throw ValidationException::withMessages([
                'labor_adjustment_reason' => 'Select a valid labor adjustment reason.',
            ]);
        }

        if ($reason === LaborAdjustmentReason::Custom) {
            $customReason = trim((string) ($data['labor_adjustment_reason_custom'] ?? ''));

            if ($customReason === '') {
                throw ValidationException::withMessages([
                    'labor_adjustment_reason_custom' => 'Describe the custom labor adjustment reason.',
                ]);
            }

            return $customReason;
        }

        return $reason->label();
    }

    private function resolveAdjustmentFactor(LaborAdjustment $adjustment, mixed $submittedFactor): float
    {
        if ($adjustment !== LaborAdjustment::Custom) {
            return $adjustment->defaultFactor();
        }

        if (! filled($submittedFactor)) {
            throw ValidationException::withMessages([
                'labor_adjustment_factor' => 'Enter a custom labor adjustment factor.',
            ]);
        }

        $factor = (float) $submittedFactor;

        if ($factor < 1.00 || $factor > 3.00) {
            throw ValidationException::withMessages([
                'labor_adjustment_factor' => 'Custom labor adjustment must be between 1.00 and 3.00.',
            ]);
        }

        return round($factor, 2);
    }

    private function hoursFromInput(mixed $value): float
    {
        if (! filled($value)) {
            return 0.0;
        }

        return max(0.0, round((float) $value, 2));
    }

    private function formatHours(float $hours): string
    {
        return number_format($hours, 2, '.', '');
    }

    private function formatFactor(float $factor): string
    {
        return number_format($factor, 2, '.', '');
    }
}
