<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

final class LaborWorkTypeMatrixProjection
{
    public function __construct(
        private readonly LaborPoliciesMatrixProjection $policiesMatrix,
    ) {}

    /**
     * @return array{
     *     work_types: list<array{key: string, label: string, operation_class_key: string, shares_class_with: string|null}>,
     *     columns: list<array{value: string, label: string}>,
     *     cells: array<string, array<string, array{
     *         rate_display: string,
     *         effective_rate_cents: int|null,
     *         settings_mode: string|null,
     *         follows_shop_standard: bool,
     *         operation_class_key: string,
     *     }>>,
     *     standard_rate_cents: int,
     *     billing_minimums: array<string, array{mode: string, hours: string, shortfall_rate_cents: int|null, shortfall_rate_source: string}>,
     *     labor_pricing_profile_version: int,
     * }
     */
    public function build(?Carbon $asOf = null): array
    {
        $matrix = $this->policiesMatrix->build($asOf);
        $settings = ShopSettings::current();
        $standardCents = $settings->defaultLaborRateCents();
        $minimums = LaborBillingMinimums::fromStored(
            is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
        );

        $cells = [];

        foreach (LaborWorkTypeMatrix::workTypes() as $workType) {
            $cells[$workType['key']] = [];

            foreach (LaborWorkTypeMatrix::primaryBillingColumns() as $column) {
                $policyCell = $matrix['cells'][$workType['operation_class_key']][$column['value']] ?? null;

                $cells[$workType['key']][$column['value']] = [
                    'rate_display' => $policyCell['rate_display'] ?? '—',
                    'effective_rate_cents' => $policyCell['effective_rate_cents'] ?? null,
                    'settings_mode' => $policyCell['settings_mode'] ?? null,
                    'follows_shop_standard' => (bool) ($policyCell['follows_shop_standard'] ?? false),
                    'operation_class_key' => $workType['operation_class_key'],
                ];
            }
        }

        $minimumRows = [];

        foreach (LaborWorkTypeMatrix::minimumConfigurablePostures() as $posture) {
            $row = $minimums->forPosture($posture) ?? [
                'mode' => LaborBillingMinimumMode::None->value,
                'hours' => '0.00',
                'shortfall_rate_cents' => $standardCents,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ];
            $minimumRows[$posture->value] = $row;
        }

        return [
            'work_types' => LaborWorkTypeMatrix::workTypes(),
            'columns' => array_map(
                fn (array $column): array => [
                    'value' => $column['value'],
                    'label' => $column['label'],
                ],
                LaborWorkTypeMatrix::primaryBillingColumns(),
            ),
            'cells' => $cells,
            'standard_rate_cents' => $standardCents,
            'billing_minimums' => $minimumRows,
            'labor_pricing_profile_version' => (int) ($settings->labor_pricing_profile_version ?? 0),
        ];
    }
}
