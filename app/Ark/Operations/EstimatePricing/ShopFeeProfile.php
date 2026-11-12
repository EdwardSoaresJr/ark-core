<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable shop-fee rules stamped on a repair order at creation (or lazily on first recalc).
 * null on the RO means legacy live-settings behavior until stamped.
 *
 * Schema 1: percent | flat (flat = once per RO).
 * Schema 2: adds per_concern (flat dollars once per eligible concern, optional RO cap).
 * Legacy schema-1 flat profiles must never be reinterpreted as per_concern.
 */
final class ShopFeeProfile
{
    public const MODE_PERCENT = 'percent';

    public const MODE_FLAT = 'flat';

    public const MODE_PER_CONCERN = 'per_concern';

    public const CURRENT_SCHEMA = 2;

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [self::MODE_PERCENT, self::MODE_FLAT, self::MODE_PER_CONCERN];
    }

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
        return new self(
            enabled: (bool) $settings->shop_fee_enabled,
            mode: self::normalizeMode((string) ($settings->shop_fee_mode ?? self::MODE_PERCENT)),
            rate: filled($settings->shop_fee_rate) ? (string) $settings->shop_fee_rate : '0',
            flatCents: $settings->shop_fee_flat_cents !== null ? (int) $settings->shop_fee_flat_cents : null,
            capCents: $settings->shop_fee_cap_cents !== null ? (int) $settings->shop_fee_cap_cents : null,
            taxable: (bool) $settings->taxable_shop_fees,
            schema: self::CURRENT_SCHEMA,
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

        $schema = (int) ($stored['schema'] ?? 1);
        $mode = self::normalizeMode((string) ($stored['mode'] ?? self::MODE_PERCENT), $schema);

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
            schema: $schema,
        );
    }

    public function isFlat(): bool
    {
        return $this->mode === self::MODE_FLAT;
    }

    public function isPerConcern(): bool
    {
        return $this->mode === self::MODE_PER_CONCERN;
    }

    public function usesCap(): bool
    {
        return $this->mode === self::MODE_PERCENT || $this->mode === self::MODE_PER_CONCERN;
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

    private static function normalizeMode(string $mode, int $schema = self::CURRENT_SCHEMA): string
    {
        if ($mode === self::MODE_PER_CONCERN && $schema < 2) {
            // Schema 1 never defined per_concern; refuse reinterpretation.
            return self::MODE_PERCENT;
        }

        return in_array($mode, self::modes(), true) ? $mode : self::MODE_PERCENT;
    }
}
