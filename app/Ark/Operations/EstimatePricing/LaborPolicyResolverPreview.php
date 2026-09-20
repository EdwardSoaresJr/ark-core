<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;

final class LaborPolicyResolverPreview
{
    public function __construct(
        private readonly LaborPolicyResolver $resolver,
    ) {}

    /**
     * @return array{
     *     billing_posture: string,
     *     operation_class_key: string,
     *     rate_display: string|null,
     *     hourly_rate_cents: int|null,
     *     error: string|null,
     * }
     */
    public function for(?string $billingPosture, ?string $operationClassKey, ?Carbon $asOf = null): array
    {
        $postureValue = $billingPosture
            ?: ConcernBillingPosture::CustomerPay->value;
        $classKey = $operationClassKey
            ?: (OperationClass::query()->orderBy('sort_order')->value('key') ?: 'general_repair');

        $posture = ConcernBillingPosture::tryFrom($postureValue) ?? ConcernBillingPosture::CustomerPay;

        try {
            $resolved = $this->resolver->resolve($posture, $classKey, $asOf);

            return [
                'billing_posture' => $resolved->billingPosture,
                'operation_class_key' => $resolved->operationClassKey,
                'rate_display' => '$'.number_format($resolved->hourlyRateCents / 100, 2),
                'hourly_rate_cents' => $resolved->hourlyRateCents,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'billing_posture' => $posture->value,
                'operation_class_key' => $classKey,
                'rate_display' => null,
                'hourly_rate_cents' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
