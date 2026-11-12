<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpsertLaborPolicyAction
{
    public function execute(
        ConcernBillingPosture $billingPosture,
        OperationClass $operationClass,
        float $hourlyRate,
        Carbon $effectiveFrom,
        ?string $changeReason = null,
        ?LaborRateType $rateType = null,
    ): LaborPolicy {
        $rateCents = (int) round($hourlyRate * 100);
        $effectiveDate = $effectiveFrom->toDateString();
        $resolvedType = $this->resolveRateType($billingPosture, $rateCents, $rateType);

        if ($resolvedType === LaborRateType::Standard) {
            $rateCents = ShopSettings::current()->defaultLaborRateCents();
        }

        if ($resolvedType === LaborRateType::Zero) {
            $rateCents = 0;
        }

        return DB::transaction(function () use (
            $billingPosture,
            $operationClass,
            $rateCents,
            $resolvedType,
            $effectiveDate,
            $changeReason,
        ): LaborPolicy {
            $current = LaborPolicy::query()
                ->where('billing_posture', $billingPosture->value)
                ->where('operation_class_id', $operationClass->id)
                ->whereDate('effective_from', '<=', $effectiveDate)
                ->where(function ($query) use ($effectiveDate): void {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $effectiveDate);
                })
                ->orderBy('priority')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $sameRate = $current !== null
                && $current->rate_type === $resolvedType
                && (int) ($current->hourly_rate_cents ?? 0) === $rateCents
                && $current->effective_from?->toDateString() === $effectiveDate;

            if ($sameRate) {
                $current->update([
                    'change_reason' => $changeReason,
                    'rate_type' => $resolvedType,
                    'hourly_rate_cents' => $rateCents,
                ]);

                return $current->fresh();
            }

            $nextVersion = $current === null ? 1 : ((int) $current->version + 1);

            if ($current !== null) {
                $until = Carbon::parse($effectiveDate)->subDay()->toDateString();

                if ($current->effective_from?->toDateString() <= $until) {
                    $current->update(['effective_until' => $until]);
                } else {
                    $current->update(['effective_until' => $current->effective_from]);
                }
            }

            return LaborPolicy::query()->create([
                'billing_posture' => $billingPosture->value,
                'operation_class_id' => $operationClass->id,
                'rate_type' => $resolvedType,
                'hourly_rate_cents' => $rateCents,
                'effective_from' => $effectiveDate,
                'effective_until' => null,
                'priority' => 100,
                'version' => $nextVersion,
                'change_reason' => $changeReason,
            ]);
        });
    }

    private function resolveRateType(
        ConcernBillingPosture $billingPosture,
        int $rateCents,
        ?LaborRateType $rateType,
    ): LaborRateType {
        if ($rateType instanceof LaborRateType) {
            return $rateType;
        }

        if ($rateCents === 0) {
            return LaborRateType::Zero;
        }

        if (in_array($billingPosture, [ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::RepairPal], true)) {
            return LaborRateType::Contract;
        }

        return LaborRateType::Hourly;
    }
}
