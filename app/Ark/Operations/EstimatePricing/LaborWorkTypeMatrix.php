<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;

/**
 * Owner-facing work type × billing class matrix rows/columns.
 * Rates still resolve/save through Operation Class policies — see design trace.
 */
final class LaborWorkTypeMatrix
{
    /**
     * @return list<array{key: string, label: string, operation_class_key: string, shares_class_with: string|null}>
     */
    public static function workTypes(): array
    {
        return [
            [
                'key' => 'mechanical',
                'label' => 'Mechanical',
                'operation_class_key' => 'general_repair',
                'shares_class_with' => null,
            ],
            [
                'key' => 'advanced_mechanical',
                'label' => 'Advanced Mechanical',
                'operation_class_key' => 'advanced_mechanical',
                'shares_class_with' => null,
            ],
            [
                'key' => 'diagnostic',
                'label' => 'Troubleshooting',
                'operation_class_key' => 'diagnostics',
                'shares_class_with' => null,
            ],
            [
                'key' => 'programming',
                'label' => 'Programming',
                'operation_class_key' => 'diagnostics',
                'shares_class_with' => 'diagnostic',
            ],
        ];
    }

    /**
     * Primary matrix columns (user-facing Retail = Customer Pay).
     *
     * @return list<array{value: string, label: string, posture: ConcernBillingPosture}>
     */
    public static function primaryBillingColumns(): array
    {
        return [
            [
                'value' => ConcernBillingPosture::CustomerPay->value,
                'label' => 'Retail',
                'posture' => ConcernBillingPosture::CustomerPay,
            ],
            [
                'value' => ConcernBillingPosture::WarrantyOther->value,
                'label' => 'Warranty',
                'posture' => ConcernBillingPosture::WarrantyOther,
            ],
            [
                'value' => ConcernBillingPosture::Fleet->value,
                'label' => 'Fleet',
                'posture' => ConcernBillingPosture::Fleet,
            ],
            [
                'value' => ConcernBillingPosture::RepairPal->value,
                'label' => 'RepairPal',
                'posture' => ConcernBillingPosture::RepairPal,
            ],
        ];
    }

    /**
     * @return list<ConcernBillingPosture>
     */
    public static function minimumConfigurablePostures(): array
    {
        return [
            ConcernBillingPosture::CustomerPay,
            ConcernBillingPosture::WarrantyOther,
            ConcernBillingPosture::Fleet,
            ConcernBillingPosture::RepairPal,
            ConcernBillingPosture::Wholesale,
            ConcernBillingPosture::Internal,
            ConcernBillingPosture::Comeback,
        ];
    }

    public static function billingColumnLabel(ConcernBillingPosture $posture): string
    {
        return match ($posture) {
            ConcernBillingPosture::CustomerPay => 'Retail',
            ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::Warranty => 'Warranty',
            default => $posture->label(),
        };
    }
}
