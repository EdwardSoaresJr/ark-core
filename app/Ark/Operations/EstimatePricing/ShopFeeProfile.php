<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable shop-fee rules stamped on a repair order at creation (or lazily on first recalc).
 * null on the RO means legacy live-settings behavior until stamped.
 */
final class ShopFeeProfile
{
    public const MODE_PERCENT = 'percent';

    public const MODE_FLAT = 'flat';

    public const CURRENT_SCHEMA = 1;

    public function __construct(
        public readonly bool $enabled,
        public readonly string $mode,
        public readonly string $rate,
        public readonly ?int $flatCents,
        public readonly ?int $capCents,
        public readonly bool $taxable,
        public readonly int $schema = self::CURRENT_SCHEMA,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $mode = (string) ($settings->shop_fee_mode ?? self::MODE_PERCENT);
        if (! in_array($mode, [self::MODE_PERCENT, self::MODE_FLAT], true)) {
            $mode = self::MODE_PERCENT;
        }

        return new self(
            enabled: (bool) $settings->shop_fee_enabled,
            mode: $mode,
            rate: filled($settings->shop_fee_rate) ? (string) $settings->shop_fee_rate : '0',
            flatCents: $settings->shop_fee_flat_cents !== null ? (int) $settings->shop_fee_flat_cents : null,
            capCents: $settings->shop_fee_cap_cents !== null ? (int) $settings->shop_fee_cap_cents : null,
            taxable: (bool) $settings->taxable_shop_fees,
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

        $mode = (string) ($stored['mode'] ?? self::MODE_PERCENT);
        if (! in_array($mode, [self::MODE_PERCENT, self::MODE_FLAT], true)) {
            $mode = self::MODE_PERCENT;
        }

        return new self(
            enabled: (bool) ($stored['enabled'] ?? false),
            mode: $mode,
            rate: (string) ($stored['rate'] ?? '0'),
            flatCents: array_key_exists('flat_cents', $stored) && $stored['flat_cents'] !== null
                ? (int) $stored['flat_cents']
                : null,
            capCents: array_key_exists('cap_cents', $stored) && $stored['cap_cents'] !== null
                ? (int) $stored['cap_cents']
                : null,
            taxable: (bool) ($stored['taxable'] ?? false),
            schema: (int) ($stored['schema'] ?? self::CURRENT_SCHEMA),
        );
    }

    public function isFlat(): bool
    {
        return $this->mode === self::MODE_FLAT;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     mode: string,
     *     rate: string,
     *     flat_cents: int|null,
     *     cap_cents: int|null,
     *     taxable: bool,
     *     schema: int
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'mode' => $this->mode,
            'rate' => $this->rate,
            'flat_cents' => $this->flatCents,
            'cap_cents' => $this->capCents,
            'taxable' => $this->taxable,
            'schema' => $this->schema,
        ];
    }
}
