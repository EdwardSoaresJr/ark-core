<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;

/**
 * Ensures Customer Pay policies that explicitly follow the shop standard stay on
 * rate_type=standard. Specialty custom / zero / contract cells are never overwritten.
 *
 * Standard policies resolve dynamically from ShopSettings::defaultLaborRateCents(),
 * so changing the standard rate does not require rewriting every Standard policy row
 * beyond keeping rate_type=standard and a display mirror of the cents.
 */
class SyncStandardCustomerPayLaborRatesAction
{
    public function __construct(
        private readonly UpsertLaborPolicyAction $upsertLaborPolicy,
        private readonly LaborPoliciesMatrixProjection $matrixProjection,
    ) {}

    /**
     * @return array{
     *     updated_class_keys: list<string>,
     *     preserved_class_keys: list<string>,
     *     previous_standard_cents: int,
     *     next_standard_cents: int,
     * }
     */
    public function execute(
        int $previousStandardCents,
        int $nextStandardCents,
        ?Carbon $effectiveFrom = null,
        ?string $changeReason = null,
    ): array {
        $effectiveFrom ??= Carbon::now()->startOfDay();
        $reason = $changeReason ?? 'Standard hourly rate';
        $matrix = $this->matrixProjection->build($effectiveFrom);

        $updated = [];
        $preserved = [];

        foreach ($matrix['operation_classes'] as $class) {
            $classKey = $class['key'];
            $cell = $matrix['cells'][$classKey][ConcernBillingPosture::CustomerPay->value] ?? null;
            $rateType = LaborRateType::tryFrom((string) ($cell['rate_type'] ?? '')) ?? LaborRateType::Hourly;
            $currentCents = $cell['hourly_rate_cents'] ?? null;

            $followsStandard = $rateType === LaborRateType::Standard
                || (
                    // Pre-migration / legacy: Customer Pay hourly that still matched the prior standard.
                    $rateType === LaborRateType::Hourly
                    && ($currentCents === null || (int) $currentCents === $previousStandardCents)
                );

            if (! $followsStandard) {
                $preserved[] = $classKey;

                continue;
            }

            $operationClass = OperationClass::query()->where('key', $classKey)->firstOrFail();
            $this->upsertLaborPolicy->execute(
                ConcernBillingPosture::CustomerPay,
                $operationClass,
                $nextStandardCents / 100,
                $effectiveFrom,
                $reason,
                LaborRateType::Standard,
            );
            $updated[] = $classKey;
        }

        return [
            'updated_class_keys' => $updated,
            'preserved_class_keys' => $preserved,
            'previous_standard_cents' => $previousStandardCents,
            'next_standard_cents' => $nextStandardCents,
        ];
    }
}
