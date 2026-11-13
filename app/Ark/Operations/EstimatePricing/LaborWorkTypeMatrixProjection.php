<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Labor\LaborRoundingRule;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

final class LaborWorkTypeMatrixProjection
{
    public function __construct(
        private readonly LaborPolicyResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $asOf = null): array
    {
        $settings = ShopSettings::current();
        $standardCents = $settings->defaultLaborRateCents();
        $minimums = LaborBillingMinimums::fromStored(
            is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
        );
        $categoriesByKey = collect($settings->laborCategories())->keyBy('key');

        $workTypes = [];
        $cells = [];
        $workTypeSettings = [];

        foreach (LaborWorkTypeMatrix::workTypes($settings) as $workType) {
            $workTypes[] = $workType;
            $cells[$workType['key']] = [];
            $category = $categoriesByKey->get($workType['labor_category_key']) ?? null;
            $rounding = LaborRoundingRule::tryFrom((string) ($category['rounding_rule'] ?? ''))
                ?? LaborRoundingRule::Quarter;
            $workTypeSettings[$workType['key']] = [
                'labor_category_key' => $workType['labor_category_key'],
                // Line billed-hours floor + rounding live on the work type's labor category (not per billing class).
                'line_minimum_hours' => (string) ($category['minimum_hours'] ?? '0.00'),
                'rounding_rule' => $rounding->value,
                // Enforcement stays in LaborAuthority; not shown as a matrix control (#194).
                'allows_modifiers' => (bool) ($category['allows_modifiers'] ?? true),
            ];

            foreach (LaborWorkTypeMatrix::matrixBillingColumns($settings) as $column) {
                try {
                    $resolved = $this->resolver->resolve($column['posture'], $workType['operation_class_key'], $asOf);
                    $cells[$workType['key']][$column['value']] = [
                        'rate_display' => '$'.number_format($resolved->hourlyRateCents / 100, 2),
                        'effective_rate_cents' => $resolved->hourlyRateCents,
                        'settings_mode' => $resolved->rateType->settingsMode(),
                        'follows_shop_standard' => $resolved->rateType->followsShopStandard(),
                        'operation_class_key' => $workType['operation_class_key'],
                        'is_default_cell' => $workType['key'] === 'mechanical'
                            && $column['value'] === ConcernBillingPosture::CustomerPay->value,
                    ];
                } catch (\Throwable) {
                    $cells[$workType['key']][$column['value']] = [
                        'rate_display' => '—',
                        'effective_rate_cents' => null,
                        'settings_mode' => 'custom',
                        'follows_shop_standard' => false,
                        'operation_class_key' => $workType['operation_class_key'],
                        'is_default_cell' => false,
                    ];
                }
            }
        }

        $minimumRows = [];
        $columns = [];

        foreach (LaborWorkTypeMatrix::matrixBillingColumns($settings) as $column) {
            $customerClassKey = CustomerBillingClassIdentity::classKeyForLaborColumn($column['value']);
            $columns[] = [
                'value' => $column['value'],
                'label' => $column['label'],
                'is_system' => $column['is_system'],
                'is_default' => $column['is_default'],
                'customer_class_key' => $customerClassKey,
                'has_customer_class' => $customerClassKey !== null,
            ];
            $key = LaborBillingMinimums::postureKey($column['posture']);
            $minimumRows[$key] = $minimums->forPosture($key) ?? [
                'mode' => LaborBillingMinimumMode::None->value,
                'hours' => '0.00',
                'shortfall_rate_cents' => $standardCents,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ];
        }

        $billingClasses = collect($settings->customerTypeRows())
            ->map(fn (array $row): array => [
                'key' => $row['key'],
                'name' => $row['name'],
                'labor_column_key' => $row['labor_column_key'] ?? null,
                'customer_only' => CustomerBillingClassIdentity::isCustomerOnlyClassKey($row['key'] ?? null),
            ])
            ->values()
            ->all();

        return [
            'work_types' => $workTypes,
            'columns' => $columns,
            'cells' => $cells,
            'work_type_settings' => $workTypeSettings,
            'rounding_options' => collect(LaborRoundingRule::cases())
                ->map(fn (LaborRoundingRule $rule): array => [
                    'value' => $rule->value,
                    'label' => $rule->label(),
                ])
                ->values()
                ->all(),
            'standard_rate_cents' => $standardCents,
            'billing_minimums' => $minimumRows,
            'billing_classes' => $billingClasses,
            'labor_pricing_profile_version' => (int) ($settings->labor_pricing_profile_version ?? 0),
            'routes' => [
                'rate' => route('operations.settings.shop.labor-matrix.rate'),
                'minimum' => route('operations.settings.shop.labor-matrix.minimum'),
                'work_type_rounding' => route('operations.settings.shop.labor-matrix.work-type-rounding'),
                'work_type' => route('operations.settings.shop.labor-matrix.work-types.store'),
                'billing_class' => route('operations.settings.shop.labor-matrix.billing-classes.store'),
                'customer_types_show' => route('operations.settings.shop.customer-types.show'),
                'customer_type_update' => route('operations.settings.shop.customer-types.item.update', ['classKey' => '__KEY__']),
                'customer_types_store' => route('operations.settings.shop.customer-types.store'),
            ],
        ];
    }
}
