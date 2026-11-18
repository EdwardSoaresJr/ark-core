<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Read model over the one billing-class registry (shop_settings.customer_types[]) for every
 * place a class is chosen: customer assignment, concern override, labor matrix, commercial rules (#217).
 */
final class CanonicalBillingClassCatalog
{
    public const RETAIL_LABOR_COLUMN = 'customer_pay';

    /**
     * Active classes a customer can be assigned.
     *
     * @return list<array{key: string, name: string, labor_column_key: string|null, is_active: bool}>
     */
    public static function selectableForCustomers(ShopSettings $settings): array
    {
        return collect($settings->customerTypeRows())
            ->filter(fn (array $row): bool => (bool) ($row['is_active'] ?? true))
            ->map(fn (array $row): array => [
                'key' => (string) $row['key'],
                'name' => (string) $row['name'],
                'labor_column_key' => filled($row['labor_column_key'] ?? null) ? (string) $row['labor_column_key'] : null,
                'is_active' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Active classes a concern can override to. `labor_column_key` is the value stored on the concern.
     * Legacy labor-only custom columns (no customer class link) stay selectable so existing
     * shops lose nothing; they are reported, never merged.
     *
     * @return list<array{key: string, label: string, labor_column_key: string}>
     */
    public static function selectableForConcerns(ShopSettings $settings): array
    {
        $customColumns = collect($settings->allCustomLaborBillingClasses())->keyBy('key');
        $options = [];
        $linked = [];

        foreach ($settings->customerTypeRows() as $row) {
            if (! (bool) ($row['is_active'] ?? true) || ! filled($row['labor_column_key'] ?? null)) {
                continue;
            }

            $column = (string) $row['labor_column_key'];
            $custom = $customColumns->get($column);
            if ($custom !== null && ! $custom['is_active']) {
                continue;
            }

            $linked[] = $column;
            $options[] = [
                'key' => (string) $row['key'],
                'label' => (string) $row['name'],
                'labor_column_key' => $column,
            ];
        }

        foreach ($customColumns as $key => $custom) {
            if ($custom['is_active'] && ! in_array($key, $linked, true)) {
                $options[] = [
                    'key' => $key,
                    'label' => $custom['label'],
                    'labor_column_key' => $key,
                ];
            }
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function selectableConcernLaborColumnKeys(ShopSettings $settings): array
    {
        return array_values(array_unique(array_column(self::selectableForConcerns($settings), 'labor_column_key')));
    }

    /**
     * Display label for a stored labor column key: catalog class name first, then the custom
     * column label, then the built-in label (customer_pay is Retail).
     */
    public static function labelForLaborColumn(?string $laborColumnKey, ?ShopSettings $settings = null): string
    {
        $settings ??= ShopSettings::current();
        $key = trim((string) $laborColumnKey);

        if ($key === '' || $key === ConcernBillingPosture::Default->value) {
            $key = self::RETAIL_LABOR_COLUMN;
        }

        $linked = collect($settings->customerTypeRows())
            ->first(fn (array $row): bool => ($row['labor_column_key'] ?? null) === $key);
        if (is_array($linked)) {
            return (string) $linked['name'];
        }

        $custom = $settings->customLaborBillingClass($key);
        if (is_array($custom)) {
            return (string) $custom['label'];
        }

        return LaborWorkTypeMatrix::billingColumnLabel(
            ConcernBillingPosture::tryFrom($key) ?? $key,
            $settings,
        );
    }

    /**
     * Labor column for a customer class row. The explicit link wins. A legacy unlinked class
     * that named its concern billing keeps that; anything else prices at Retail.
     *
     * @param  array<string, mixed>|null  $row
     */
    public static function laborColumnForClassRow(?array $row): string
    {
        if (filled($row['labor_column_key'] ?? null)) {
            return (string) $row['labor_column_key'];
        }

        $legacy = ConcernBillingPosture::tryFrom((string) ($row['concern_billing_posture'] ?? ''));
        if ($legacy instanceof ConcernBillingPosture && $legacy !== ConcernBillingPosture::Default) {
            return $legacy->value;
        }

        return self::RETAIL_LABOR_COLUMN;
    }

    /**
     * Fee/parts posture an inheriting concern behaves as. Retail, custom and unlinked classes stay
     * on Default so the customer class row (by name) supplies their fees and parts matrix.
     */
    public static function postureForLaborColumn(?string $laborColumnKey): ConcernBillingPosture
    {
        $enum = ConcernBillingPosture::tryFrom((string) $laborColumnKey);

        return $enum instanceof ConcernBillingPosture
            && ! in_array($enum, [ConcernBillingPosture::Default, ConcernBillingPosture::CustomerPay], true)
                ? ConcernBillingPosture::fromStored($enum->value)
                : ConcernBillingPosture::Default;
    }
}
