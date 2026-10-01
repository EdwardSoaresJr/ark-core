<?php

namespace App\Ark\Operations\EstimatePricing;

final readonly class ResolvedLaborRate
{
    public function __construct(
        public int $hourlyRateCents,
        public LaborRateType $rateType,
        public string $billingPosture,
        public string $operationClassKey,
        public ?int $laborPolicyId,
        public ?int $laborPolicyVersion,
    ) {}

    /**
     * @return array{
     *     labor_rate_cents: int,
     *     resolved_from_posture: string,
     *     resolved_from_operation_class: string,
     *     labor_policy_id: int|null,
     *     labor_policy_version: int|null,
     * }
     */
    public function toLineSnapshot(): array
    {
        return [
            'labor_rate_cents' => $this->hourlyRateCents,
            'resolved_from_posture' => $this->billingPosture,
            'resolved_from_operation_class' => $this->operationClassKey,
            'labor_policy_id' => $this->laborPolicyId,
            'labor_policy_version' => $this->laborPolicyVersion,
        ];
    }
}
