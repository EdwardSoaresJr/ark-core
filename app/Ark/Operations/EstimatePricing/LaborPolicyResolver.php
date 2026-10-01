<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Single path for labor rate resolution. Estimate lines store the resolved result only.
 */
final class LaborPolicyResolver
{
    public function resolve(
        ConcernBillingPosture|string $billingPosture,
        string $operationClassKey,
        ?CarbonInterface $asOf = null,
    ): ResolvedLaborRate {
        $posture = $this->normalizePosture($billingPosture);
        $asOfDate = ($asOf ?? Carbon::now())->toDateString();
        $classKey = trim($operationClassKey);

        $operationClass = OperationClass::query()->where('key', $classKey)->first();

        if ($operationClass === null) {
            throw new RuntimeException("Unknown operation class [{$classKey}].");
        }

        $policy = LaborPolicy::query()
            ->where('billing_posture', $posture->value)
            ->where('operation_class_id', $operationClass->id)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(function ($query) use ($asOfDate): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $asOfDate);
            })
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($policy === null) {
            throw new RuntimeException(
                "No labor policy for posture [{$posture->value}] and operation class [{$classKey}] on [{$asOfDate}].",
            );
        }

        return new ResolvedLaborRate(
            hourlyRateCents: $this->hourlyCentsFromPolicy($policy),
            rateType: $policy->rate_type,
            billingPosture: $posture->value,
            operationClassKey: $operationClass->key,
            laborPolicyId: $policy->id,
            laborPolicyVersion: $policy->version,
        );
    }

    private function normalizePosture(ConcernBillingPosture|string $billingPosture): ConcernBillingPosture
    {
        $posture = $billingPosture instanceof ConcernBillingPosture
            ? $billingPosture
            : (ConcernBillingPosture::tryFrom((string) $billingPosture) ?? ConcernBillingPosture::CustomerPay);

        return match ($posture) {
            ConcernBillingPosture::Default => ConcernBillingPosture::CustomerPay,
            ConcernBillingPosture::Warranty => ConcernBillingPosture::WarrantyOther,
            ConcernBillingPosture::WarrantyRepairPal => ConcernBillingPosture::RepairPal,
            default => $posture,
        };
    }

    private function hourlyCentsFromPolicy(LaborPolicy $policy): int
    {
        return match ($policy->rate_type) {
            LaborRateType::Zero => 0,
            LaborRateType::Hourly, LaborRateType::Contract => (int) ($policy->hourly_rate_cents ?? 0),
            LaborRateType::Cost => (int) ($policy->hourly_rate_cents ?? 0),
        };
    }
}
