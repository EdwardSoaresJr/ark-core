<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintains RO-scoped billing-class minimum Fee lines from the RO's stamped profile.
 * Never mutates original labor hours or technician production quantities.
 *
 * @phpstan-type ChangeNotice array{posture: string, label: string, from_hours: string, to_hours: string}
 */
final class ApplyBillingClassMinimumsAction
{
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

        foreach (LaborWorkTypeMatrix::minimumConfigurablePostures() as $posture) {
            $row = $minimums->forPosture($posture);

            if ($row === null) {
                continue;
            }

            $mode = LaborBillingMinimumMode::tryFrom($row['mode']) ?? LaborBillingMinimumMode::None;

            if ($mode === LaborBillingMinimumMode::None || (float) $row['hours'] <= 0) {
                $this->removePostureMinimumFees($repairOrder, $posture);

                continue;
            }

            $rateCents = $minimums->shortfallRateCents($posture, $settings);
            $label = LaborWorkTypeMatrix::billingColumnLabel($posture);

            if ($mode === LaborBillingMinimumMode::PerRepairOrder) {
                $notice = $this->syncPerRepairOrderMinimum(
                    $repairOrder,
                    $posture,
                    (float) $row['hours'],
                    $rateCents,
                    $label,
                );

                if ($notice !== null) {
                    $notices[] = $notice;
                }

                continue;
            }

            $lineNotices = $this->syncPerLineMinimums(
                $repairOrder,
                $posture,
                (float) $row['hours'],
                $rateCents,
                $label,
            );
            array_push($notices, ...$lineNotices);
        }

        return $notices;
    }

    /**
     * @return ChangeNotice|null
     */
    private function syncPerRepairOrderMinimum(
        RepairOrder $repairOrder,
        ConcernBillingPosture $posture,
        float $requiredHours,
        int $rateCents,
        string $label,
    ): ?array {
        $eligible = $this->eligibleLaborHours($repairOrder, $posture);
        $shortfall = max(0, round($requiredHours - $eligible, 2));
        $description = self::SYSTEM_DESCRIPTION_PREFIX.$label;
        $existing = $this->systemMinimumFees($repairOrder, $posture)->first();
        $previousShortfall = $existing ? (float) $existing->quantity : 0.0;

        if ($shortfall < 0.005) {
            if ($existing) {
                $existing->delete();
            }

            if ($previousShortfall >= 0.005) {
                return [
                    'posture' => $posture->value,
                    'label' => $label,
                    'from_hours' => number_format($previousShortfall, 2, '.', ''),
                    'to_hours' => '0.00',
                ];
            }

            return null;
        }

        $concern = $this->attributionConcern($repairOrder, $posture);

        if ($concern === null) {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $unitPrice = $rateCents;
        $subtotal = (int) round($shortfall * $unitPrice);

        if ($existing) {
            $existing->update([
                'repair_order_concern_id' => $concern->id,
                'description' => $description,
                'quantity' => number_format($shortfall, 2, '.', ''),
                'unit_price_cents' => $unitPrice,
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'shop_fee_cents' => 0,
                'standing_discount_cents' => 0,
                'total_cents' => $subtotal,
            ]);
        } else {
            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'type' => RepairOrderLineType::Fee,
                'description' => $description,
                'quantity' => number_format($shortfall, 2, '.', ''),
                'unit_price_cents' => $unitPrice,
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'shop_fee_cents' => 0,
                'standing_discount_cents' => 0,
                'total_cents' => $subtotal,
            ]);
        }

        if (abs($previousShortfall - $shortfall) >= 0.005) {
            return [
                'posture' => $posture->value,
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
        ConcernBillingPosture $posture,
        float $requiredHours,
        int $rateCents,
        string $label,
    ): array {
        $notices = [];
        $laborLines = $this->eligibleLaborLines($repairOrder, $posture);
        $keptKeys = [];

        foreach ($laborLines as $laborLine) {
            $billed = (float) ($laborLine->labor_billed_hours ?? $laborLine->quantity ?? 0);
            $shortfall = max(0, round($requiredHours - $billed, 2));
            $description = self::SYSTEM_DESCRIPTION_PREFIX.$label.' · line '.$laborLine->id;
            $keptKeys[] = $description;
            $existing = $this->systemMinimumFees($repairOrder, $posture)
                ->first(fn (RepairOrderLine $fee): bool => $fee->description === $description);
            $previous = $existing ? (float) $existing->quantity : 0.0;

            if ($shortfall < 0.005) {
                if ($existing) {
                    $existing->delete();
                }

                if ($previous >= 0.005) {
                    $notices[] = [
                        'posture' => $posture->value,
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
                    'type' => RepairOrderLineType::Fee,
                    ...$payload,
                ]);
            }

            if (abs($previous - $shortfall) >= 0.005) {
                $notices[] = [
                    'posture' => $posture->value,
                    'label' => $label,
                    'from_hours' => number_format($previous, 2, '.', ''),
                    'to_hours' => number_format($shortfall, 2, '.', ''),
                ];
            }
        }

        foreach ($this->systemMinimumFees($repairOrder, $posture) as $fee) {
            if (! in_array($fee->description, $keptKeys, true)
                && str_contains($fee->description, ' · line ')
            ) {
                $fee->delete();
            }
        }

        // Remove any prior per-RO fee for this posture when in per-line mode.
        $perRoDescription = self::SYSTEM_DESCRIPTION_PREFIX.$label;
        $this->systemMinimumFees($repairOrder, $posture)
            ->filter(fn (RepairOrderLine $fee): bool => $fee->description === $perRoDescription)
            ->each(fn (RepairOrderLine $fee) => $fee->delete());

        return $notices;
    }

    public function eligibleLaborHours(RepairOrder $repairOrder, ConcernBillingPosture $posture): float
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
    public function eligibleLaborLines(RepairOrder $repairOrder, ConcernBillingPosture $posture): Collection
    {
        $postureValue = $this->normalizePosture($posture)->value;

        return $repairOrder->concerns
            ->filter(function (RepairOrderConcern $concern) use ($postureValue): bool {
                $concernPosture = $concern->billing_posture instanceof ConcernBillingPosture
                    ? $this->normalizePosture($concern->billing_posture)
                    : ConcernBillingPosture::CustomerPay;

                return $concernPosture->value === $postureValue;
            })
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->lines)
            ->filter(function (RepairOrderLine $line): bool {
                if ($line->type !== RepairOrderLineType::Labor) {
                    return false;
                }

                if ($this->isSystemMinimumFee($line)) {
                    return false;
                }

                // Zero-priced intentional labor does not satisfy a billing-class minimum.
                return (int) ($line->labor_rate_cents ?? $line->unit_price_cents ?? 0) > 0;
            })
            ->values();
    }

    private function attributionConcern(RepairOrder $repairOrder, ConcernBillingPosture $posture): ?RepairOrderConcern
    {
        $postureValue = $this->normalizePosture($posture)->value;

        return $repairOrder->concerns
            ->filter(function (RepairOrderConcern $concern) use ($postureValue): bool {
                $concernPosture = $concern->billing_posture instanceof ConcernBillingPosture
                    ? $this->normalizePosture($concern->billing_posture)
                    : ConcernBillingPosture::CustomerPay;

                return $concernPosture->value === $postureValue;
            })
            ->sortBy('id')
            ->first(function (RepairOrderConcern $concern) use ($repairOrder, $posture): bool {
                return $this->eligibleLaborLines($repairOrder, $posture)
                    ->contains(fn (RepairOrderLine $line): bool => (int) $line->repair_order_concern_id === (int) $concern->id);
            });
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function systemMinimumFees(RepairOrder $repairOrder, ConcernBillingPosture $posture): Collection
    {
        $label = LaborWorkTypeMatrix::billingColumnLabel($posture);
        $prefix = self::SYSTEM_DESCRIPTION_PREFIX.$label;

        return $repairOrder->lines()
            ->where('type', RepairOrderLineType::Fee->value)
            ->where('description', 'like', $prefix.'%')
            ->get();
    }

    public function isSystemMinimumFee(RepairOrderLine $line): bool
    {
        return $line->type === RepairOrderLineType::Fee
            && str_starts_with($line->description, self::SYSTEM_DESCRIPTION_PREFIX);
    }

    /**
     * @return list<ChangeNotice>
     */
    private function removeAllSystemMinimumFees(RepairOrder $repairOrder): array
    {
        $removed = RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', RepairOrderLineType::Fee->value)
            ->where('description', 'like', self::SYSTEM_DESCRIPTION_PREFIX.'%')
            ->get();

        foreach ($removed as $line) {
            $line->delete();
        }

        return [];
    }

    private function removePostureMinimumFees(RepairOrder $repairOrder, ConcernBillingPosture $posture): void
    {
        foreach ($this->systemMinimumFees($repairOrder, $posture) as $fee) {
            $fee->delete();
        }
    }

    private function normalizePosture(ConcernBillingPosture $posture): ConcernBillingPosture
    {
        return match ($posture) {
            ConcernBillingPosture::Default => ConcernBillingPosture::CustomerPay,
            ConcernBillingPosture::Warranty => ConcernBillingPosture::WarrantyOther,
            ConcernBillingPosture::WarrantyRepairPal => ConcernBillingPosture::RepairPal,
            default => $posture,
        };
    }
}
