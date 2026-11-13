<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Labor\LaborRoundingRule;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Shop-level Work Type × Billing Class rounding configuration.
 *
 * @phpstan-type RoundingRows array<string, array<string, string>>
 */
final class LaborRoundingMatrix
{
    /**
     * @param  RoundingRows  $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {}

    public static function disabled(): self
    {
        return new self([]);
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromStored(?array $raw): self
    {
        if ($raw === null || $raw === []) {
            return self::disabled();
        }

        $rows = [];

        foreach ($raw as $workType => $cells) {
            if (! is_string($workType) || $workType === '' || ! is_array($cells)) {
                continue;
            }

            foreach ($cells as $billingClass => $rule) {
                if (! is_string($billingClass) || $billingClass === '') {
                    continue;
                }

                $parsed = LaborRoundingRule::tryFrom((string) $rule);
                if ($parsed === null) {
                    continue;
                }

                $rows[$workType][$billingClass] = $parsed->value;
            }
        }

        return new self($rows);
    }

    /**
     * Seed every Work Type × Billing Class cell from the work type's category rounding_rule.
     */
    public static function fromShopCategories(ShopSettings $settings): self
    {
        $categories = collect($settings->laborCategories())->keyBy('key');
        $rows = [];

        foreach (LaborWorkTypeMatrix::workTypes($settings) as $workType) {
            $category = $categories->get($workType['labor_category_key']);
            $fallback = LaborRoundingRule::tryFrom((string) ($category['rounding_rule'] ?? ''))
                ?? LaborRoundingRule::Quarter;

            foreach (LaborWorkTypeMatrix::matrixBillingColumns($settings) as $column) {
                $rows[$workType['key']][$column['value']] = $fallback->value;
            }
        }

        return new self($rows);
    }

    public function ruleFor(string $workTypeKey, ConcernBillingPosture|string $billingClass): ?LaborRoundingRule
    {
        $billingKey = $billingClass instanceof ConcernBillingPosture
            ? $billingClass->value
            : (string) $billingClass;

        $billingKey = match ($billingKey) {
            'default' => ConcernBillingPosture::CustomerPay->value,
            'warranty' => ConcernBillingPosture::WarrantyOther->value,
            'warranty_repairpal' => ConcernBillingPosture::RepairPal->value,
            default => $billingKey,
        };

        $value = $this->rows[$workTypeKey][$billingKey] ?? null;

        return is_string($value) ? LaborRoundingRule::tryFrom($value) : null;
    }

    public function withRule(string $workTypeKey, string $billingClass, LaborRoundingRule $rule): self
    {
        $rows = $this->rows;
        $rows[$workTypeKey][$billingClass] = $rule->value;

        return new self($rows);
    }

    /**
     * Ensure every current matrix cell exists; fill gaps from category fallback.
     */
    public function ensureComplete(ShopSettings $settings): self
    {
        $seeded = self::fromShopCategories($settings)->toArray();
        $rows = $seeded;

        foreach ($this->rows as $workType => $cells) {
            foreach ($cells as $billingClass => $rule) {
                $rows[$workType][$billingClass] = $rule;
            }
        }

        return new self($rows);
    }

    /**
     * @return RoundingRows
     */
    public function toArray(): array
    {
        return $this->rows;
    }
}
