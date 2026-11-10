<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable tax rules stamped on a repair order at creation.
 * null on the RO means legacy live-settings behavior until stamped (living only).
 * Terminal ROs must not invent tax rules from live settings.
 */
final class TaxProfile
{
    public const CURRENT_SCHEMA = 1;

    public function __construct(
        public readonly bool $enabled,
        public readonly string $label,
        public readonly string $rate,
        public readonly bool $taxableLabor,
        public readonly bool $taxableParts,
        public readonly bool $taxableShopFees,
        public readonly int $schema = self::CURRENT_SCHEMA,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        return new self(
            enabled: (bool) $settings->tax_enabled,
            label: filled($settings->tax_label) ? (string) $settings->tax_label : 'Tax',
            rate: filled($settings->default_tax_rate) ? (string) $settings->default_tax_rate : '0',
            taxableLabor: (bool) $settings->taxable_labor,
            taxableParts: (bool) $settings->taxable_parts,
            taxableShopFees: (bool) $settings->taxable_shop_fees,
        );
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function tryFromStored(?array $stored): ?self
    {
        if ($stored === null || $stored === []) {
            return null;
        }

        return new self(
            enabled: (bool) ($stored['enabled'] ?? false),
            label: filled($stored['label'] ?? null) ? (string) $stored['label'] : 'Tax',
            rate: (string) ($stored['rate'] ?? '0'),
            taxableLabor: (bool) ($stored['taxable_labor'] ?? false),
            taxableParts: (bool) ($stored['taxable_parts'] ?? true),
            taxableShopFees: (bool) ($stored['taxable_shop_fees'] ?? false),
            schema: (int) ($stored['schema'] ?? self::CURRENT_SCHEMA),
        );
    }

    /**
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     rate: string,
     *     taxable_labor: bool,
     *     taxable_parts: bool,
     *     taxable_shop_fees: bool,
     *     schema: int
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'label' => $this->label,
            'rate' => $this->rate,
            'taxable_labor' => $this->taxableLabor,
            'taxable_parts' => $this->taxableParts,
            'taxable_shop_fees' => $this->taxableShopFees,
            'schema' => $this->schema,
        ];
    }
}
