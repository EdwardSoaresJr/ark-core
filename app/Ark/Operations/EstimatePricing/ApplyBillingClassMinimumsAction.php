<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;

/**
 * Maintains RO-scoped billing-class minimum Fee lines from the RO's stamped profile.
 * Never mutates original labor hours or technician production quantities.
 * Identity uses system_line_kind / system_line_scope — not description prefixes alone.
 *
 * @phpstan-type ChangeNotice array{posture: string, label: string, from_hours: string, to_hours: string}
 */
final class ApplyBillingClassMinimumsAction
{
    public const SYSTEM_KIND = 'billing_minimum';

    public const SYSTEM_DESCRIPTION_PREFIX = 'Billing minimum · ';

    /**
     * @return list<ChangeNotice>
     */
    public function synchronize(RepairOrder $repairOrder): array
    {
        $profile = LaborPricingProfile::tryFromStored(
            is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
        );

        if ($profile === null || ! $profile->appliesBillingClassMinimums()) {
            return $this->removeAllSystemMinimumFees($repairOrder);
        }

        $settings = ShopSettings::current();
        $minimums = $profile->minimums();
        $notices = [];

        $repairOrder->loadMissing(['concerns.lines']);

        // Use the RO-stamped minimum keys so historical custom classes still resolve.
        $scopes = array_keys($minimums->toArray());

        if ($scopes === []) {
            $scopes = array_map(
                fn (ConcernBillingPosture|string $posture): string => LaborBillingMinimums::postureKey($posture),
                LaborWorkTypeMatrix::minimumConfigurablePostures($settings),
            );
        }

        foreach ($scopes as $scope) {
            $row = $minimums->forPosture($scope);

            if ($row === null) {
                $this->removePostureMinimumFees($repairOrder, $scope);

                continue;
            }

            $mode = LaborBillingMinimumMode::tryFrom($row['mode']) ?? LaborBillingMinimumMode::None;

            if ($mode === LaborBillingMinimumMode::None || (float) $row['hours'] <= 0) {
                $this->removePostureMinimumFees($repairOrder, $scope);

                continue;
            }

            $rateCents = $minimums->shortfallRateCents($scope, $settings);
            $label = LaborWorkTypeMatrix::billingColumnLabel($scope, $settings);

            if ($mode === LaborBillingMinimumMode::PerRepairOrder) {
                $notice = $this->syncPerRepairOrderMinimum(
                    $repairOrder,
                    $scope,
                    (float) $row['hours'],
                    $rateCents,
                    $label,
                );

                if ($notice !== null) {
                    $notices[] = $notice;
                }

                continue;
            }

            if ($mode === LaborBillingMinimumMode::PerConcern) {
                array_push(
                    $notices,
                    ...$this->syncPerConcernMinimums(
                        $repairOrder,
                        $scope,
                        (float) $row['hours'],
                        $rateCents,
                        $label,
                    ),
                );

                continue;
            }

            array_push(
                $notices,
                ...$this->syncPerLineMinimums(
                    $repairOrder,
                    $scope,
                    (float) $row['hours'],
                    $rateCents,
                    $label,
                ),
            );
        }

        return $notices;
    }

    /**
     * @return ChangeNotice|null
     */
    private function syncPerRepairOrderMinimum(
        RepairOrder $repairOrder,
        string $scope,
        float $requiredHours,
        int $rateCents,
        string $label,
    ): ?array {
        $eligible = $this->eligibleLaborHours($repairOrder, $scope);
        $shortfall = max(0, round($requiredHours - $eligible, 2));
        $description = self::SYSTEM_DESCRIPTION_PREFIX.$label;
        $existing = $this->systemMinimumFees($repairOrder, $scope)
            ->first(function (RepairOrderLine $fee) use ($scope): bool {
                $scopeValue = (string) ($fee->system_line_scope ?? '');

                if ($scopeValue !== '' && $scopeValue !== $scope) {
                    return false;
                }

                return ! str_contains((string) $fee->description, ' · line ')
                    && ! str_contains((string) $fee->description, ' · concern ');
            });
        $previousShortfall = $existing ? (float) $existing->quantity : 0.0;

        if ($shortfall < 0.005) {
            if ($existing) {
                $existing->delete();
            }

            if ($previousShortfall >= 0.005) {
                return [
                    'posture' => $scope,
                    'label' => $label,
                    'from_hours' => number_format($previousShortfall, 2, '.', ''),
                    'to_hours' => '0.00',
                ];
            }

            return null;
        }

        $concern = $this->attributionConcern($repairOrder, $scope);

        if ($concern === null) {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $subtotal = (int) round($shortfall * $rateCents);
        $payload = [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Fee,
            'system_line_kind' => self::SYSTEM_KIND,
            'system_line_scope' => $scope,
            'description' => $description,
            'quantity' => number_format($shortfall, 2, '.', ''),
            'unit_price_cents' => $rateCents,
            'subtotal_cents' => $subtotal,
            'tax_cents' => 0,
            'shop_fee_cents' => 0,
            'standing_discount_cents' => 0,
            'total_cents' => $subtotal,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                ...$payload,
            ]);
        }

        foreach ($this->systemMinimumFees($repairOrder, $scope) as $fee) {
            $scopeValue = (string) ($fee->system_line_scope ?? '');
            if (str_contains($scopeValue, '|line:') || str_contains($scopeValue, '|concern:')) {
                $fee->delete();
            }
        }

        if (abs($previousShortfall - $shortfall) >= 0.005) {
            return [
                'posture' => $scope,
                'label' => $label,
                'from_hours' => number_format($previousShortfall, 2, '.', ''),
                'to_hours' => number_format($shortfall, 2, '.', ''),
            ];
        }

        return null;
    }

    /**
     * @return list<ChangeNotice>
     */
    private function syncPerLineMinimums(
        RepairOrder $repairOrder,
        string $scope,
        float $requiredHours,
        int $rateCents,
        string $label,
    ): array {
        $notices = [];
        $laborLines = $this->eligibleLaborLines($repairOrder, $scope);
        $keptLineIds = [];

        foreach ($laborLines as $laborLine) {
            $billed = (float) ($laborLine->labor_billed_hours ?? $laborLine->quantity ?? 0);
            $shortfall = max(0, round($requiredHours - $billed, 2));
            $lineScope = $scope.'|line:'.$laborLine->id;
            $keptLineIds[] = $laborLine->id;
            $existing = $this->systemMinimumFees($repairOrder, $scope)
                ->first(fn (RepairOrderLine $fee): bool => (string) $fee->system_line_scope === $lineScope
                    || $fee->description === self::SYSTEM_DESCRIPTION_PREFIX.$label.' · line '.$laborLine->id);
            $previous = $existing ? (float) $existing->quantity : 0.0;

            if ($shortfall < 0.005) {
                if ($existing) {
                    $existing->delete();
                }

                if ($previous >= 0.005) {
                    $notices[] = [
                        'posture' => $scope,
                        'label' => $label,
                        'from_hours' => number_format($previous, 2, '.', ''),
                        'to_hours' => '0.00',
                    ];
                }

                continue;
            }

            $subtotal = (int) round($shortfall * $rateCents);
            $payload = [
                'repair_order_concern_id' => $laborLine->repair_order_concern_id,
                'type' => RepairOrderLineType::Fee,
                'system_line_kind' => self::SYSTEM_KIND,
                'system_line_scope' => $lineScope,
                'description' => self::SYSTEM_DESCRIPTION_PREFIX.$label.' · line '.$laborLine->id,
                'quantity' => number_format($shortfall, 2, '.', ''),
                'unit_price_cents' => $rateCents,
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'shop_fee_cents' => 0,
                'standing_discount_cents' => 0,
                'total_cents' => $subtotal,
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                RepairOrderLine::query()->create([
                    'repair_order_id' => $repairOrder->id,
                    ...$payload,
                ]);
            }

            if (abs($previous - $shortfall) >= 0.005) {
                $notices[] = [
                    'posture' => $scope,
                    'label' => $label,
                    'from_hours' => number_format($previous, 2, '.', ''),
                    'to_hours' => number_format($shortfall, 2, '.', ''),
                ];
            }
        }

        foreach ($this->systemMinimumFees($repairOrder, $scope) as $fee) {
            $scopeValue = (string) ($fee->system_line_scope ?? '');
            if (str_contains($scopeValue, '|line:')) {
                $lineId = (int) substr($scopeValue, strrpos($scopeValue, ':') + 1);
                if (! in_array($lineId, $keptLineIds, true)) {
                    $fee->delete();
                }
            } elseif (
                $scopeValue === $scope
                || str_contains($scopeValue, '|concern:')
                || $fee->description === self::SYSTEM_DESCRIPTION_PREFIX.$label
            ) {
                // Remove prior per-RO / per-concern fees when in per-line mode.
                $fee->delete();
            }
        }

        return $notices;
    }

    /**
     * @return list<ChangeNotice>
     */
    private function syncPerConcernMinimums(
        RepairOrder $repairOrder,
        string $scope,
        float $requiredHours,
        int $rateCents,
        string $label,
    ): array {
        $notices = [];
        $keptConcernIds = [];

        $concerns = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $this->concernBillingKey($concern) === $scope)
            ->sortBy('id')
            ->values();

        foreach ($concerns as $concern) {
            $eligibleLines = $this->eligibleLaborLines($repairOrder, $scope)
                ->filter(fn (RepairOrderLine $line): bool => (int) $line->repair_order_concern_id === (int) $concern->id);

            if ($eligibleLines->isEmpty()) {
                continue;
            }

            $keptConcernIds[] = (int) $concern->id;
            $billed = round(
                $eligibleLines->sum(fn (RepairOrderLine $line): float => (float) ($line->labor_billed_hours ?? $line->quantity ?? 0)),
                2,
            );
            $shortfall = max(0, round($requiredHours - $billed, 2));
            $concernScope = $scope.'|concern:'.$concern->id;
            $existing = $this->systemMinimumFees($repairOrder, $scope)
                ->first(fn (RepairOrderLine $fee): bool => (string) $fee->system_line_scope === $concernScope
                    || (
                        (int) $fee->repair_order_concern_id === (int) $concern->id
                        && ! str_contains((string) $fee->system_line_scope, '|line:')
                        && (string) $fee->system_line_scope !== $scope
                    ));
            $previous = $existing ? (float) $existing->quantity : 0.0;

            if ($shortfall < 0.005) {
                if ($existing) {
                    $existing->delete();
                }

                if ($previous >= 0.005) {
                    $notices[] = [
                        'posture' => $scope,
                        'label' => $label,
                        'from_hours' => number_format($previous, 2, '.', ''),
                        'to_hours' => '0.00',
                    ];
                }

                continue;
            }

            $subtotal = (int) round($shortfall * $rateCents);
            $payload = [
                'repair_order_concern_id' => $concern->id,
                'type' => RepairOrderLineType::Fee,
                'system_line_kind' => self::SYSTEM_KIND,
                'system_line_scope' => $concernScope,
                'description' => self::SYSTEM_DESCRIPTION_PREFIX.$label.' · concern '.$concern->id,
                'quantity' => number_format($shortfall, 2, '.', ''),
                'unit_price_cents' => $rateCents,
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'shop_fee_cents' => 0,
                'standing_discount_cents' => 0,
                'total_cents' => $subtotal,
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                RepairOrderLine::query()->create([
                    'repair_order_id' => $repairOrder->id,
                    ...$payload,
                ]);
            }

            if (abs($previous - $shortfall) >= 0.005) {
                $notices[] = [
                    'posture' => $scope,
                    'label' => $label,
                    'from_hours' => number_format($previous, 2, '.', ''),
                    'to_hours' => number_format($shortfall, 2, '.', ''),
                ];
            }
        }

        foreach ($this->systemMinimumFees($repairOrder, $scope) as $fee) {
            $scopeValue = (string) ($fee->system_line_scope ?? '');
            if (str_contains($scopeValue, '|concern:')) {
                $concernId = (int) substr($scopeValue, strrpos($scopeValue, ':') + 1);
                if (! in_array($concernId, $keptConcernIds, true)) {
                    $fee->delete();
                }
            } elseif (
                $scopeValue === $scope
                || str_contains($scopeValue, '|line:')
                || $fee->description === self::SYSTEM_DESCRIPTION_PREFIX.$label
            ) {
                // Remove prior per-RO / per-line fees when in per-concern mode.
                $fee->delete();
            }
        }

        return $notices;
    }

    public function eligibleLaborHours(RepairOrder $repairOrder, ConcernBillingPosture|string $posture): float
    {
        return round(
            $this->eligibleLaborLines($repairOrder, $posture)
                ->sum(fn (RepairOrderLine $line): float => (float) ($line->labor_billed_hours ?? $line->quantity ?? 0)),
            2,
        );
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    public function eligibleLaborLines(RepairOrder $repairOrder, ConcernBillingPosture|string $posture): Collection
    {
        $postureValue = LaborBillingMinimums::postureKey($posture);

        return $repairOrder->concerns
            ->filter(function (RepairOrderConcern $concern) use ($postureValue): bool {
                return $this->concernBillingKey($concern) === $postureValue;
            })
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->lines)
            ->filter(function (RepairOrderLine $line): bool {
                if ($line->type !== RepairOrderLineType::Labor) {
                    return false;
                }

                if ($this->isSystemMinimumFee($line)) {
                    return false;
                }

                return (int) ($line->labor_rate_cents ?? $line->unit_price_cents ?? 0) > 0;
            })
            ->values();
    }

    /**
     * RO-wide billing-class minimum notices (PerRepairOrder mode) — show once near totals.
     *
     * @return list<array{label: string, mode: string, required_hours: string, eligible_hours: string, shortfall_hours: string, shortfall_cents: int, applied: bool}>
     */
    public function repairOrderWideExplanations(RepairOrder $repairOrder): array
    {
        $profile = LaborPricingProfile::tryFromStored(
            is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
        );

        if ($profile === null || ! $profile->appliesBillingClassMinimums()) {
            return [];
        }

        $seen = [];
        $explanations = [];

        foreach ($repairOrder->concerns as $concern) {
            $scope = $this->concernBillingKey($concern);
            if (isset($seen[$scope])) {
                continue;
            }

            $row = $profile->minimums()->forPosture($scope);
            if ($row === null) {
                continue;
            }

            $mode = LaborBillingMinimumMode::tryFrom($row['mode']) ?? LaborBillingMinimumMode::None;
            if ($mode !== LaborBillingMinimumMode::PerRepairOrder || (float) $row['hours'] <= 0) {
                continue;
            }

            $seen[$scope] = true;
            $required = (float) $row['hours'];
            $eligible = $this->eligibleLaborHours($repairOrder, $scope);
            $shortfall = max(0, round($required - $eligible, 2));
            $rateCents = $profile->minimums()->shortfallRateCents($scope, ShopSettings::current());

            $explanations[] = [
                'label' => LaborWorkTypeMatrix::billingColumnLabel($scope),
                'mode' => $mode->value,
                'required_hours' => number_format($required, 2, '.', ''),
                'eligible_hours' => number_format($eligible, 2, '.', ''),
                'shortfall_hours' => number_format($shortfall, 2, '.', ''),
                'shortfall_cents' => (int) round($shortfall * $rateCents),
                'applied' => $shortfall >= 0.005,
            ];
        }

        return $explanations;
    }

    /**
     * Persistent advisor explanation for a concern (survives reload).
     * Per-repair-order minimums are excluded — use {@see repairOrderWideExplanations()}.
     *
     * @return array{label: string, mode: string, required_hours: string, eligible_hours: string, shortfall_hours: string, shortfall_cents: int, applied: bool}|null
     */
    public function concernExplanation(RepairOrder $repairOrder, RepairOrderConcern $concern): ?array
    {
        $profile = LaborPricingProfile::tryFromStored(
            is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
        );

        if ($profile === null || ! $profile->appliesBillingClassMinimums()) {
            return null;
        }

        $scope = $this->concernBillingKey($concern);
        $row = $profile->minimums()->forPosture($scope);

        if ($row === null) {
            return null;
        }

        $mode = LaborBillingMinimumMode::tryFrom($row['mode']) ?? LaborBillingMinimumMode::None;

        // RO-wide minimums are rendered once near totals, not inside every concern.
        if (
            ! in_array($mode, [LaborBillingMinimumMode::PerLine, LaborBillingMinimumMode::PerConcern], true)
            || (float) $row['hours'] <= 0
        ) {
            return null;
        }

        $required = (float) $row['hours'];
        $rateCents = $profile->minimums()->shortfallRateCents($scope, ShopSettings::current());
        $lineEligible = $this->eligibleLaborLines($repairOrder, $scope)
            ->filter(fn (RepairOrderLine $line): bool => (int) $line->repair_order_concern_id === (int) $concern->id)
            ->sum(fn (RepairOrderLine $line): float => (float) ($line->labor_billed_hours ?? $line->quantity ?? 0));
        $lineShortfall = $this->systemMinimumFees($repairOrder, $scope)
            ->filter(fn (RepairOrderLine $fee): bool => (int) $fee->repair_order_concern_id === (int) $concern->id)
            ->sum(fn (RepairOrderLine $fee): float => (float) $fee->quantity);

        return [
            'label' => LaborWorkTypeMatrix::billingColumnLabel($scope),
            'mode' => $mode->value,
            'required_hours' => number_format($required, 2, '.', ''),
            'eligible_hours' => number_format($lineEligible, 2, '.', ''),
            'shortfall_hours' => number_format($lineShortfall, 2, '.', ''),
            'shortfall_cents' => (int) round($lineShortfall * $rateCents),
            'applied' => $lineShortfall >= 0.005,
        ];
    }

    private function attributionConcern(RepairOrder $repairOrder, string $postureValue): ?RepairOrderConcern
    {
        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $this->concernBillingKey($concern) === $postureValue)
            ->sortBy('id')
            ->first(function (RepairOrderConcern $concern) use ($repairOrder, $postureValue): bool {
                return $this->eligibleLaborLines($repairOrder, $postureValue)
                    ->contains(fn (RepairOrderLine $line): bool => (int) $line->repair_order_concern_id === (int) $concern->id);
            });
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function systemMinimumFees(RepairOrder $repairOrder, string $scope): Collection
    {
        return $repairOrder->lines()
            ->where('type', RepairOrderLineType::Fee->value)
            ->where(function ($query) use ($scope): void {
                        $query->where(function ($inner) use ($scope): void {
                    $inner->where('system_line_kind', self::SYSTEM_KIND)
                        ->where(function ($scopeQuery) use ($scope): void {
                            $scopeQuery->where('system_line_scope', $scope)
                                ->orWhere('system_line_scope', 'like', $scope.'|line:%')
                                ->orWhere('system_line_scope', 'like', $scope.'|concern:%');
                        });
                })->orWhere(function ($legacy) use ($scope): void {
                    $label = LaborWorkTypeMatrix::billingColumnLabel($scope);
                    $legacy->whereNull('system_line_kind')
                        ->where('description', 'like', self::SYSTEM_DESCRIPTION_PREFIX.$label.'%');
                });
            })
            ->get();
    }

    public function isSystemMinimumFee(RepairOrderLine $line): bool
    {
        if ($line->type !== RepairOrderLineType::Fee) {
            return false;
        }

        if ($line->system_line_kind === self::SYSTEM_KIND) {
            return true;
        }

        return str_starts_with((string) $line->description, self::SYSTEM_DESCRIPTION_PREFIX);
    }

    /**
     * @return list<ChangeNotice>
     */
    private function removeAllSystemMinimumFees(RepairOrder $repairOrder): array
    {
        RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', RepairOrderLineType::Fee->value)
            ->where(function ($query): void {
                $query->where('system_line_kind', self::SYSTEM_KIND)
                    ->orWhere('description', 'like', self::SYSTEM_DESCRIPTION_PREFIX.'%');
            })
            ->delete();

        return [];
    }

    private function removePostureMinimumFees(RepairOrder $repairOrder, string $scope): void
    {
        foreach ($this->systemMinimumFees($repairOrder, $scope) as $fee) {
            $fee->delete();
        }
    }

    private function concernBillingKey(RepairOrderConcern $concern): string
    {
        $raw = $concern->getAttributes()['billing_posture'] ?? null;

        if (is_string($raw) && $raw !== '') {
            return LaborBillingMinimums::postureKey($raw);
        }

        $posture = $concern->billing_posture instanceof ConcernBillingPosture
            ? $concern->billing_posture
            : ConcernBillingPosture::CustomerPay;

        return LaborBillingMinimums::postureKey($posture);
    }
}
