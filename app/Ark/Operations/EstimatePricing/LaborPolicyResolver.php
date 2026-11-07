<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderReadCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $operationClass = $this->operationClasses()->get($classKey);

        if ($operationClass === null) {
            throw new RuntimeException("Unknown operation class [{$classKey}].");
        }

        $policy = $this->policies()->first(
            fn (LaborPolicy $policy): bool => $policy->billing_posture === $posture->value
                && (int) $policy->operation_class_id === (int) $operationClass->id
                && $policy->effective_from->toDateString() <= $asOfDate
                && ($policy->effective_until === null || $policy->effective_until->toDateString() >= $asOfDate),
        );

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

    /**
     * Every labor policy in resolution order, read once per request: a repair order shows the
     * policy rate of each labor category on each of its concerns.
     *
     * @return Collection<int, LaborPolicy>
     */
    private function policies(): Collection
    {
        return app(RepairOrderReadCache::class)->remember('labor-policies', fn (): Collection => LaborPolicy::query()
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get());
    }

    /**
     * @return Collection<string, OperationClass>
     */
    public function operationClasses(): Collection
    {
        return app(RepairOrderReadCache::class)->remember('operation-classes', fn (): Collection => OperationClass::query()
            ->get()
            ->keyBy('key'));
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
