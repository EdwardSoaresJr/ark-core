<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpsertLaborPolicyAction
{
    public function execute(
        ConcernBillingPosture $billingPosture,
        OperationClass $operationClass,
        float $hourlyRate,
        Carbon $effectiveFrom,
        ?string $changeReason = null,
    ): LaborPolicy {
        $rateCents = (int) round($hourlyRate * 100);
        $rateType = $rateCents === 0 ? LaborRateType::Zero : LaborRateType::Hourly;
        $effectiveDate = $effectiveFrom->toDateString();

        return DB::transaction(function () use (
            $billingPosture,
            $operationClass,
            $rateCents,
            $rateType,
            $effectiveDate,
            $changeReason,
        ): LaborPolicy {
            if (in_array($billingPosture, [ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::RepairPal], true)
                && $rateCents > 0
            ) {
                $rateType = LaborRateType::Contract;
            }

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

            if ($current !== null
                && (int) ($current->hourly_rate_cents ?? 0) === $rateCents
                && $current->effective_from?->toDateString() === $effectiveDate
            ) {
                $current->update([
                    'change_reason' => $changeReason,
                    'rate_type' => $rateType,
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
                'rate_type' => $rateType,
                'hourly_rate_cents' => $rateCents,
                'effective_from' => $effectiveDate,
                'effective_until' => null,
                'priority' => 100,
                'version' => $nextVersion,
                'change_reason' => $changeReason,
            ]);
        });
    }
}
