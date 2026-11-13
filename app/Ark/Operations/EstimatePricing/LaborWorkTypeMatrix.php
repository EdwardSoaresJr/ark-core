<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Owner-facing work type × billing class matrix rows/columns.
 * Rates still resolve/save through Operation Class policies.
 */
final class LaborWorkTypeMatrix
{
    /**
     * @return list<array{key: string, label: string, operation_class_key: string, labor_category_key: string, shares_class_with: string|null, is_default: bool, is_system: bool}>
     */
    public static function workTypes(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $builtIn = [
            [
                'key' => 'mechanical',
                'label' => 'Mechanical',
                'operation_class_key' => 'general_repair',
                'labor_category_key' => 'mechanical',
                'shares_class_with' => null,
                'is_default' => true,
                'is_system' => true,
            ],
            [
                'key' => 'advanced_mechanical',
                'label' => 'Advanced Mechanical',
                'operation_class_key' => 'advanced_mechanical',
                'labor_category_key' => 'advanced_mechanical',
                'shares_class_with' => null,
                'is_default' => false,
                'is_system' => true,
            ],
            [
                'key' => 'diagnostic',
                'label' => 'Troubleshooting',
                'operation_class_key' => 'diagnostics',
                'labor_category_key' => 'diagnostic',
                'shares_class_with' => null,
                'is_default' => false,
                'is_system' => true,
            ],
            [
                'key' => 'programming',
                'label' => 'Programming',
                'operation_class_key' => 'programming',
                'labor_category_key' => 'programming',
                'shares_class_with' => null,
                'is_default' => false,
                'is_system' => true,
            ],
        ];

        $reservedCategoryKeys = [
            'mechanical',
            'advanced_mechanical',
            'diagnostic',
            'programming',
            'fabrication',
            'courtesy',
            ShopSettings::COMEBACK_LABOR_CATEGORY_KEY,
            ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
            ShopSettings::WARRANTY_OTHER_LABOR_CATEGORY_KEY,
        ];

        $categoriesByKey = collect($settings->laborCategories())->keyBy('key');
        $isActive = function (string $key) use ($categoriesByKey): bool {
            $category = $categoriesByKey->get($key);

            return $category === null || (bool) ($category['is_active'] ?? true);
        };

        $builtIn = array_values(array_filter(
            $builtIn,
            fn (array $row): bool => $isActive($row['key']),
        ));

        $custom = collect($settings->laborCategories())
            ->reject(fn (array $category): bool => in_array($category['key'], $reservedCategoryKeys, true))
            ->filter(fn (array $category): bool => (bool) ($category['is_active'] ?? true))
            ->map(function (array $category) use ($settings): array {
                $operationClassKey = self::operationClassKeyForCategory($category['key'], $settings);

                return [
                    'key' => $category['key'],
                    'label' => $category['name'],
                    'operation_class_key' => $operationClassKey,
                    'labor_category_key' => $category['key'],
                    'shares_class_with' => null,
                    'is_default' => false,
                    'is_system' => false,
                ];
            })
            ->values()
            ->all();

        // Keep fabrication as a matrix work type when active.
        $fabrication = $categoriesByKey->get('fabrication');
        if (is_array($fabrication) && ($fabrication['is_active'] ?? true)) {
            $builtIn[] = [
                'key' => 'fabrication',
                'label' => $fabrication['name'] ?? 'Fabrication',
                'operation_class_key' => self::operationClassKeyForCategory('fabrication', $settings),
                'labor_category_key' => 'fabrication',
                'shares_class_with' => null,
                'is_default' => false,
                'is_system' => true,
            ];
        }

        return [...$builtIn, ...$custom];
    }

    public static function operationClassKeyForCategory(string $categoryKey, ?ShopSettings $settings = null): string
    {
        $operation = \App\Ark\Operations\OperationAuthority\Operation::query()
            ->with('operationClass')
            ->where('code', $categoryKey)
            ->where('is_active', true)
            ->first();

        return $operation?->operationClass?->key
            ?? match ($categoryKey) {
                'mechanical' => 'general_repair',
                'advanced_mechanical' => 'advanced_mechanical',
                'diagnostic' => 'diagnostics',
                'programming' => 'programming',
                default => 'general_repair',
            };
    }

    public static function workTypeKeyForLaborCategory(string $categoryKey, ?ShopSettings $settings = null): string
    {
        foreach (self::workTypes($settings) as $workType) {
            if ($workType['labor_category_key'] === $categoryKey || $workType['key'] === $categoryKey) {
                return $workType['key'];
            }
        }

        return $categoryKey;
    }

    /**
     * Primary matrix columns for the selected work type editor.
     *
     * @return list<array{value: string, label: string, posture: ConcernBillingPosture|string, is_system: bool, is_default: bool}>
     */
    public static function matrixBillingColumns(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();

        $builtIn = [
            [
                'value' => ConcernBillingPosture::CustomerPay->value,
                'label' => 'Retail',
                'posture' => ConcernBillingPosture::CustomerPay,
                'is_system' => true,
                'is_default' => true,
            ],
            [
                'value' => ConcernBillingPosture::WarrantyOther->value,
                'label' => 'Warranty',
                'posture' => ConcernBillingPosture::WarrantyOther,
                'is_system' => true,
                'is_default' => false,
            ],
            [
                'value' => ConcernBillingPosture::Fleet->value,
                'label' => 'Fleet',
                'posture' => ConcernBillingPosture::Fleet,
                'is_system' => true,
                'is_default' => false,
            ],
            [
                'value' => ConcernBillingPosture::RepairPal->value,
                'label' => 'RepairPal',
                'posture' => ConcernBillingPosture::RepairPal,
                'is_system' => true,
                'is_default' => false,
            ],
            [
                'value' => ConcernBillingPosture::Wholesale->value,
                'label' => 'Wholesale',
                'posture' => ConcernBillingPosture::Wholesale,
                'is_system' => true,
                'is_default' => false,
            ],
            [
                'value' => ConcernBillingPosture::Internal->value,
                'label' => 'Internal',
                'posture' => ConcernBillingPosture::Internal,
                'is_system' => true,
                'is_default' => false,
            ],
            [
                'value' => ConcernBillingPosture::Comeback->value,
                'label' => 'Comeback',
                'posture' => ConcernBillingPosture::Comeback,
                'is_system' => true,
                'is_default' => false,
            ],
        ];

        $custom = collect($settings->customLaborBillingClasses())
            ->map(fn (array $class): array => [
                'value' => $class['key'],
                'label' => $class['label'],
                'posture' => $class['key'],
                'is_system' => false,
                'is_default' => false,
            ])
            ->values()
            ->all();

        return [...$builtIn, ...$custom];
    }

    /**
     * @return list<ConcernBillingPosture|string>
     */
    public static function minimumConfigurablePostures(?ShopSettings $settings = null): array
    {
        return array_map(
            fn (array $column) => $column['posture'],
            self::matrixBillingColumns($settings),
        );
    }

    public static function billingColumnLabel(ConcernBillingPosture|string $posture, ?ShopSettings $settings = null): string
    {
        if ($posture instanceof ConcernBillingPosture) {
            return match ($posture) {
                ConcernBillingPosture::CustomerPay => 'Retail',
                ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::Warranty => 'Warranty',
                default => $posture->label(),
            };
        }

        foreach (self::matrixBillingColumns($settings) as $column) {
            if ($column['value'] === $posture) {
                return $column['label'];
            }
        }

        return (string) $posture;
    }

    /**
     * @deprecated Use matrixBillingColumns(); kept for older call sites.
     *
     * @return list<array{value: string, label: string, posture: ConcernBillingPosture}>
     */
    public static function primaryBillingColumns(): array
    {
        return array_values(array_filter(
            self::matrixBillingColumns(),
            fn (array $column): bool => in_array($column['value'], [
                ConcernBillingPosture::CustomerPay->value,
                ConcernBillingPosture::WarrantyOther->value,
                ConcernBillingPosture::Fleet->value,
                ConcernBillingPosture::RepairPal->value,
            ], true),
        ));
    }
}
