<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable labor pricing rules stamped on a repair order at creation.
 * null / missing on the RO means legacy behavior (no billing-class minimums;
 * new lines resolve policies as-of opened_at).
 *
 * Profile version 2 adds a frozen rates matrix so Standard inheritance cannot
 * pull a later shop default onto an already-created RO.
 */
final class LaborPricingProfile
{
    public const CURRENT_SCHEMA = 2;

    /**
     * @param  array<string, mixed>  $billingMinimums
     * @param  array<string, array<string, array{mode: string, rate_cents: int, operation_class_key: string}>>  $rates
     * @param  array<string, array{key: string, label: string, operation_class_key: string, labor_category_key: string}>  $workTypes
     * @param  array<string, mixed>  $laborCategories
     */
    public function __construct(
        public readonly int $version,
        public readonly array $billingMinimums,
        public readonly int $defaultLaborRateCents,
        public readonly array $rates = [],
        public readonly array $workTypes = [],
        public readonly array $laborCategories = [],
        public readonly int $schema = self::CURRENT_SCHEMA,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $minimums = LaborBillingMinimums::fromStored(
            is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
        );
        $resolver = app(LaborPolicyResolver::class);
        $rates = [];
        $workTypes = [];

        foreach (LaborWorkTypeMatrix::workTypes($settings) as $workType) {
            $workTypes[$workType['key']] = [
                'key' => $workType['key'],
                'label' => $workType['label'],
                'operation_class_key' => $workType['operation_class_key'],
                'labor_category_key' => $workType['labor_category_key'],
            ];
            $rates[$workType['key']] = [];

            foreach (LaborWorkTypeMatrix::matrixBillingColumns($settings) as $column) {
                try {
                    $resolved = $resolver->resolve($column['posture'], $workType['operation_class_key']);
                    $rates[$workType['key']][$column['value']] = [
                        'mode' => $resolved->rateType->settingsMode(),
                        'rate_cents' => $resolved->hourlyRateCents,
                        'operation_class_key' => $workType['operation_class_key'],
                    ];
                } catch (\Throwable) {
                    $rates[$workType['key']][$column['value']] = [
                        'mode' => 'custom',
                        'rate_cents' => $settings->defaultLaborRateCents(),
                        'operation_class_key' => $workType['operation_class_key'],
                    ];
                }
            }
        }

        $categories = collect($settings->laborCategories())
            ->map(fn (array $category): array => [
                'key' => $category['key'],
                'name' => $category['name'],
                'minimum_hours' => $category['minimum_hours'],
                'rounding_rule' => $category['rounding_rule'],
                'allows_modifiers' => (bool) ($category['allows_modifiers'] ?? true),
                'is_active' => (bool) ($category['is_active'] ?? true),
                'is_default' => (bool) ($category['is_default'] ?? false),
            ])
            ->values()
            ->all();

        return new self(
            version: max(1, (int) ($settings->labor_pricing_profile_version ?? 0)),
            billingMinimums: $minimums->toArray(),
            defaultLaborRateCents: $settings->defaultLaborRateCents(),
            rates: $rates,
            workTypes: $workTypes,
            laborCategories: $categories,
            schema: self::CURRENT_SCHEMA,
        );
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function tryFromStored(?array $raw): ?self
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $version = (int) ($raw['version'] ?? 0);

        if ($version < 1) {
            return null;
        }

        return new self(
            version: $version,
            billingMinimums: is_array($raw['billing_minimums'] ?? null) ? $raw['billing_minimums'] : [],
            defaultLaborRateCents: (int) ($raw['default_labor_rate_cents'] ?? 0),
            rates: is_array($raw['rates'] ?? null) ? $raw['rates'] : [],
            workTypes: is_array($raw['work_types'] ?? null) ? $raw['work_types'] : [],
            laborCategories: is_array($raw['labor_categories'] ?? null) ? $raw['labor_categories'] : [],
            schema: (int) ($raw['schema'] ?? 1),
        );
    }

    public function minimums(): LaborBillingMinimums
    {
        return LaborBillingMinimums::fromStored($this->billingMinimums);
    }

    public function rateCentsFor(string $workTypeKey, ConcernBillingPosture|string $posture): ?int
    {
        $postureValue = $posture instanceof ConcernBillingPosture ? $posture->value : (string) $posture;
        $postureValue = match ($postureValue) {
            'default' => ConcernBillingPosture::CustomerPay->value,
            'warranty' => ConcernBillingPosture::WarrantyOther->value,
            'warranty_repairpal' => ConcernBillingPosture::RepairPal->value,
            default => $postureValue,
        };

        $cell = $this->rates[$workTypeKey][$postureValue] ?? null;

        return is_array($cell) && isset($cell['rate_cents']) ? (int) $cell['rate_cents'] : null;
    }

    public function rateCentsForOperationClass(string $operationClassKey, ConcernBillingPosture|string $posture): ?int
    {
        $workTypeKey = collect($this->workTypes)
            ->firstWhere('operation_class_key', $operationClassKey)['key'] ?? null;

        if ($workTypeKey === null) {
            foreach ($this->rates as $cells) {
                foreach ($cells as $postureKey => $cell) {
                    if (($cell['operation_class_key'] ?? null) === $operationClassKey) {
                        $workTypeKey = array_key_first(array_filter(
                            $this->rates,
                            fn ($row): bool => ($row[$postureKey]['operation_class_key'] ?? null) === $operationClassKey,
                        ));
                        break 2;
                    }
                }
            }
        }

        if ($workTypeKey === null) {
            $workTypeKey = match ($operationClassKey) {
                'general_repair' => 'mechanical',
                'advanced_mechanical' => 'advanced_mechanical',
                'diagnostics' => 'diagnostic',
                'programming' => 'programming',
                default => null,
            };
        }

        return $workTypeKey !== null ? $this->rateCentsFor($workTypeKey, $posture) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'version' => $this->version,
            'default_labor_rate_cents' => $this->defaultLaborRateCents,
            'billing_minimums' => $this->billingMinimums,
            'rates' => $this->rates,
            'work_types' => $this->workTypes,
            'labor_categories' => $this->laborCategories,
        ];
    }

    public function appliesBillingClassMinimums(): bool
    {
        return $this->version >= 1 && $this->minimums()->enablesAnyMinimum();
    }

    public function hasFrozenRates(): bool
    {
        return $this->schema >= 2 && $this->rates !== [];
    }
}
