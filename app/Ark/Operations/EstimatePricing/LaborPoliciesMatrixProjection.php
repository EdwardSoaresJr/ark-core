<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\OperationAuthority\Operation;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

final class LaborPoliciesMatrixProjection
{
    /**
     * Billing postures shown as Labor Policies matrix columns.
     *
     * @return list<ConcernBillingPosture>
     */
    public static function matrixPostures(): array
    {
        return [
            ConcernBillingPosture::CustomerPay,
            ConcernBillingPosture::Fleet,
            ConcernBillingPosture::Wholesale,
            ConcernBillingPosture::Internal,
            ConcernBillingPosture::Comeback,
            ConcernBillingPosture::WarrantyOther,
            ConcernBillingPosture::RepairPal,
        ];
    }

    /**
     * @return array{
     *     operation_classes: list<array{id: int, key: string, name: string, is_shop_default: bool}>,
     *     postures: list<array{value: string, label: string}>,
     *     cells: array<string, array<string, array{
     *         hourly_rate_cents: int|null,
     *         rate_display: string,
     *         labor_policy_id: int|null,
     *         rate_type: string|null,
     *         effective_from: string|null,
     *         version: int|null,
     *     }>>,
     *     shop_default: array{
     *         labor_category_key: string,
     *         labor_category_name: string,
     *         operation_class_key: string|null,
     *         operation_class_name: string|null,
     *     },
     * }
     */
    public function build(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();
        $asOfDate = $asOf->toDateString();

        $classes = OperationClass::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'name']);

        $postures = self::matrixPostures();

        $policies = LaborPolicy::query()
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(function ($query) use ($asOfDate): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $asOfDate);
            })
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $cells = [];

        foreach ($classes as $class) {
            $cells[$class->key] = [];

            foreach ($postures as $posture) {
                $policy = $policies->first(
                    fn (LaborPolicy $row): bool => $row->operation_class_id === $class->id
                        && $row->billing_posture === $posture->value,
                );

                $cells[$class->key][$posture->value] = $policy === null
                    ? [
                        'hourly_rate_cents' => null,
                        'rate_display' => '—',
                        'labor_policy_id' => null,
                        'rate_type' => null,
                        'effective_from' => null,
                        'version' => null,
                    ]
                    : [
                        'hourly_rate_cents' => (int) ($policy->hourly_rate_cents ?? 0),
                        'rate_display' => '$'.number_format(((int) ($policy->hourly_rate_cents ?? 0)) / 100, 2),
                        'labor_policy_id' => $policy->id,
                        'rate_type' => $policy->rate_type->value,
                        'effective_from' => $policy->effective_from?->toDateString(),
                        'version' => $policy->version,
                    ];
            }
        }

        $settings = ShopSettings::current();
        $defaultCategory = $settings->defaultLaborCategory();
        $defaultOperation = Operation::query()
            ->with('operationClass')
            ->where('code', $defaultCategory['key'])
            ->where('is_active', true)
            ->first();
        $defaultClassKey = $defaultOperation?->operationClass?->key;

        return [
            'operation_classes' => $classes->map(fn (OperationClass $class): array => [
                'id' => $class->id,
                'key' => $class->key,
                'name' => $class->name,
                'is_shop_default' => $defaultClassKey !== null && $class->key === $defaultClassKey,
            ])->all(),
            'postures' => collect($postures)->map(fn (ConcernBillingPosture $posture): array => [
                'value' => $posture->value,
                'label' => $posture->label(),
            ])->all(),
            'cells' => $cells,
            'shop_default' => [
                'labor_category_key' => $defaultCategory['key'],
                'labor_category_name' => $defaultCategory['name'],
                'operation_class_key' => $defaultOperation?->operationClass?->key,
                'operation_class_name' => $defaultOperation?->operationClass?->name,
            ],
        ];
    }
}
