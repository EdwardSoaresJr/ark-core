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
     *     postures: list<array{value: string, label: string, description: string}>,
     *     cells: array<string, array<string, array{
     *         hourly_rate_cents: int|null,
     *         effective_rate_cents: int|null,
     *         rate_display: string,
     *         labor_policy_id: int|null,
     *         rate_type: string|null,
     *         settings_mode: string|null,
     *         follows_shop_standard: bool,
     *         effective_from: string|null,
     *         version: int|null,
     *     }>>,
     *     shop_default: array{
     *         labor_category_key: string,
     *         labor_category_name: string,
     *         operation_class_key: string|null,
     *         operation_class_name: string|null,
     *     },
     *     standard_rate_cents: int,
     * }
     */
    public function build(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();
        $asOfDate = $asOf->toDateString();
        $settings = ShopSettings::current();
        $standardCents = $settings->defaultLaborRateCents();

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

                $cells[$class->key][$posture->value] = $this->presentCell($policy, $standardCents);
            }
        }

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
                'description' => $this->postureDescription($posture),
            ])->all(),
            'cells' => $cells,
            'shop_default' => [
                'labor_category_key' => $defaultCategory['key'],
                'labor_category_name' => $defaultCategory['name'],
                'operation_class_key' => $defaultOperation?->operationClass?->key,
                'operation_class_name' => $defaultOperation?->operationClass?->name,
            ],
            'standard_rate_cents' => $standardCents,
        ];
    }

    /**
     * @return array{
     *     hourly_rate_cents: int|null,
     *     effective_rate_cents: int|null,
     *     rate_display: string,
     *     labor_policy_id: int|null,
     *     rate_type: string|null,
     *     settings_mode: string|null,
     *     follows_shop_standard: bool,
     *     effective_from: string|null,
     *     version: int|null,
     * }
     */
    private function presentCell(?LaborPolicy $policy, int $standardCents): array
    {
        if ($policy === null) {
            return [
                'hourly_rate_cents' => null,
                'effective_rate_cents' => null,
                'rate_display' => '—',
                'labor_policy_id' => null,
                'rate_type' => null,
                'settings_mode' => null,
                'follows_shop_standard' => false,
                'effective_from' => null,
                'version' => null,
            ];
        }

        $rateType = $policy->rate_type;
        $storedCents = (int) ($policy->hourly_rate_cents ?? 0);
        $effectiveCents = $rateType === LaborRateType::Standard
            ? $standardCents
            : ($rateType === LaborRateType::Zero ? 0 : $storedCents);

        return [
            'hourly_rate_cents' => $storedCents,
            'effective_rate_cents' => $effectiveCents,
            'rate_display' => '$'.number_format($effectiveCents / 100, 2),
            'labor_policy_id' => $policy->id,
            'rate_type' => $rateType->value,
            'settings_mode' => $rateType->settingsMode(),
            'follows_shop_standard' => $rateType->followsShopStandard(),
            'effective_from' => $policy->effective_from?->toDateString(),
            'version' => $policy->version,
        ];
    }

    private function postureDescription(ConcernBillingPosture $posture): string
    {
        return match ($posture) {
            ConcernBillingPosture::CustomerPay => 'Retail customer work billed at your shop rates.',
            ConcernBillingPosture::Fleet => 'Account / fleet work with negotiated rates.',
            ConcernBillingPosture::Wholesale => 'Dealer or wholesale partner rates.',
            ConcernBillingPosture::Internal => 'Shop-owned / internal work (often $0).',
            ConcernBillingPosture::Comeback => 'Comeback / no-charge rework (often $0).',
            ConcernBillingPosture::WarrantyOther => 'OEM / third-party warranty contract rates.',
            ConcernBillingPosture::RepairPal => 'RepairPal network contract rates.',
            default => 'Billing rate for this concern type.',
        };
    }
}
