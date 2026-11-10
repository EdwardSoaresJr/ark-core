<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable labor pricing rules stamped on a repair order at creation.
 * null / missing on the RO means legacy behavior (no billing-class minimums).
 */
final class LaborPricingProfile
{
    /**
     * @param  array<string, mixed>  $billingMinimums
     */
    public function __construct(
        public readonly int $version,
        public readonly array $billingMinimums,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $minimums = LaborBillingMinimums::fromStored(
            is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
        );

        return new self(
            version: max(1, (int) ($settings->labor_pricing_profile_version ?? 0)),
            billingMinimums: $minimums->toArray(),
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

        $minimums = is_array($raw['billing_minimums'] ?? null) ? $raw['billing_minimums'] : [];

        return new self($version, $minimums);
    }

    public function minimums(): LaborBillingMinimums
    {
        return LaborBillingMinimums::fromStored($this->billingMinimums);
    }

    /**
     * @return array{version: int, billing_minimums: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'billing_minimums' => $this->billingMinimums,
        ];
    }

    public function appliesBillingClassMinimums(): bool
    {
        return $this->version >= 1 && $this->minimums()->enablesAnyMinimum();
    }
}
