<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable billing-class commercial rules stamped on a repair order at creation.
 *
 * Freezes standing discounts, per-class shop-fee overrides, default parts-matrix keys,
 * and document presentation profiles so Billing Classes settings changes cannot reprice
 * an existing RO (including newly added lines on that RO).
 *
 * null on the RO means legacy live-settings behavior until stamped (e.g. before the first
 * forward-only Billing Classes save after this feature).
 */
final class CommercialPricingProfile
{
    public const CURRENT_SCHEMA = 1;

    /**
     * @param  list<array<string, mixed>>  $customerTypes
     */
    public function __construct(
        public readonly array $customerTypes,
        public readonly int $version = 1,
        public readonly int $schema = self::CURRENT_SCHEMA,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $rows = collect($settings->customerTypeRows())
            ->map(fn (array $row): array => self::commercialSlice($row))
            ->values()
            ->all();

        return new self(
            customerTypes: $rows,
            version: max(1, (int) ($settings->commercial_pricing_profile_version ?? 1)),
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

        $rows = collect($stored['customer_types'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['name'] ?? null))
            ->map(fn (array $row): array => self::commercialSlice($row))
            ->values()
            ->all();

        if ($rows === []) {
            return null;
        }

        return new self(
            customerTypes: $rows,
            version: (int) ($stored['version'] ?? 1),
            schema: (int) ($stored['schema'] ?? self::CURRENT_SCHEMA),
        );
    }

    /**
     * @return array{type: string, percent: string}|null
     */
    public function standingDiscountPolicy(?string $customerType): ?array
    {
        return ShopSettings::standingDiscountPolicyFromRow($this->customerTypeFor($customerType));
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForCustomerType(?string $customerType): array
    {
        $row = $this->customerTypeFor($customerType);

        if (! ($row['shop_fees_enabled'] ?? false)) {
            return ['enabled' => false, 'rate' => null];
        }

        return [
            'enabled' => true,
            'rate' => filled($row['shop_fee_rate_override'] ?? null)
                ? (string) $row['shop_fee_rate_override']
                : null,
        ];
    }

    public function documentPresentationProfileFor(?string $customerType): CustomerPartPresentationProfile
    {
        $row = $this->customerTypeFor($customerType);

        return CustomerPartPresentationProfile::fromStored(
            $row['document_presentation_profile']
                ?? CustomerPartPresentationProfile::defaultForBillingClassName($row['name'] ?? null)->value,
        );
    }

    public function defaultPartsMatrixKeyFor(?string $customerType): ?string
    {
        $key = $this->customerTypeFor($customerType)['default_parts_matrix_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    /**
     * Rows suitable for overlaying onto a ShopSettings replicate's customer_types attribute.
     *
     * @return list<array<string, mixed>>
     */
    public function customerTypeRows(): array
    {
        return $this->customerTypes;
    }

    /**
     * @return array{
     *     schema: int,
     *     version: int,
     *     customer_types: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'version' => $this->version,
            'customer_types' => $this->customerTypes,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function commercialSlice(array $row): array
    {
        $name = trim((string) ($row['name'] ?? 'Retail')) ?: 'Retail';
        $shopFeesEnabled = (bool) ($row['shop_fees_enabled'] ?? true);
        $rateOverride = $shopFeesEnabled && filled($row['shop_fee_rate_override'] ?? null)
            ? number_format((float) $row['shop_fee_rate_override'], 3, '.', '')
            : null;
        $discountType = (string) ($row['discount_type'] ?? 'none');
        if (! in_array($discountType, ['none', 'labor', 'parts', 'both'], true)) {
            $discountType = 'none';
        }

        return [
            'name' => $name,
            'shop_fees_enabled' => $shopFeesEnabled,
            'shop_fee_rate_override' => $rateOverride,
            'fee_override' => $shopFeesEnabled ? $rateOverride : 'none',
            'discount_type' => $discountType,
            'discount_amount' => $discountType === 'none'
                ? null
                : (filled($row['discount_amount'] ?? null)
                    ? number_format((float) $row['discount_amount'], 2, '.', '')
                    : null),
            'default_parts_matrix_key' => filled($row['default_parts_matrix_key'] ?? null)
                ? (string) $row['default_parts_matrix_key']
                : null,
            'document_presentation_profile' => CustomerPartPresentationProfile::fromStored(
                $row['document_presentation_profile'] ?? null,
            )->value,
            'concern_billing_posture' => filled($row['concern_billing_posture'] ?? null)
                ? (string) $row['concern_billing_posture']
                : null,
            'pill_color' => $row['pill_color'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerTypeFor(?string $customerType): array
    {
        $normalized = mb_strtolower(trim((string) $customerType));

        return collect($this->customerTypes)
            ->first(fn (array $row): bool => mb_strtolower((string) $row['name']) === $normalized)
            ?? $this->customerTypes[0]
            ?? [
                'name' => 'Retail',
                'shop_fees_enabled' => true,
                'shop_fee_rate_override' => null,
                'discount_type' => 'none',
                'discount_amount' => null,
                'default_parts_matrix_key' => null,
                'document_presentation_profile' => CustomerPartPresentationProfile::Retail->value,
            ];
    }
}
