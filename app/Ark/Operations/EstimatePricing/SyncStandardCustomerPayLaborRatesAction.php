<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Applies a shop standard hourly rate to Customer Pay policies that still match the previous standard.
 * Operation classes with a deliberately different Customer Pay rate are left alone.
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
            $currentCents = $cell['hourly_rate_cents'] ?? null;

            $matchesPreviousStandard = $currentCents === null || (int) $currentCents === $previousStandardCents;

            if (! $matchesPreviousStandard) {
                $preserved[] = $classKey;

                continue;
            }

            if ((int) $currentCents === $nextStandardCents) {
                continue;
            }

            $operationClass = OperationClass::query()->where('key', $classKey)->firstOrFail();
            $this->upsertLaborPolicy->execute(
                ConcernBillingPosture::CustomerPay,
                $operationClass,
                $nextStandardCents / 100,
                $effectiveFrom,
                $reason,
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

    /**
     * @param  Collection<int, OperationClass>|null  $classes
     * @return list<string>
     */
    public function customerPayClassKeysMatching(int $rateCents, ?Collection $classes = null): array
    {
        $classes ??= OperationClass::query()->orderBy('sort_order')->orderBy('id')->get();
        $matrix = $this->matrixProjection->build();
        $keys = [];

        foreach ($classes as $class) {
            $cell = $matrix['cells'][$class->key][ConcernBillingPosture::CustomerPay->value] ?? null;
            if (($cell['hourly_rate_cents'] ?? null) === $rateCents) {
                $keys[] = $class->key;
            }
        }

        return $keys;
    }
}
