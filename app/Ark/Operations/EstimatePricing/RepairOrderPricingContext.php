<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Parts\CustomerDocumentPresentation;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Authoritative pricing inputs for one repair order.
 *
 * Specialized profiles remain the storage shape; this context is the single
 * resolution entry so calculators never silently mix frozen RO rules with
 * today's ShopSettings for an existing RO.
 */
final class RepairOrderPricingContext
{
    public function __construct(
        public readonly RepairOrder $repairOrder,
        public readonly ShopSettings $shopSettings,
        public readonly ?LaborPricingProfile $labor,
        public readonly ?ShopFeeProfile $shopFee,
        public readonly ?CommercialPricingProfile $commercial,
        public readonly ?TaxProfile $tax,
        public readonly bool $terminal,
        public readonly bool $allowsLiveCommercialFallback,
    ) {}

    public static function for(RepairOrder $repairOrder, ?ShopSettings $settings = null): self
    {
        $settings ??= ShopSettings::current();
        $terminal = $repairOrder->isTerminal();

        $labor = LaborPricingProfile::tryFromStored(
            is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
        );
        $shopFee = ShopFeeProfile::tryFromStored(
            is_array($repairOrder->shop_fee_profile) ? $repairOrder->shop_fee_profile : null,
        );
        $commercial = CommercialPricingProfile::tryFromStored(
            is_array($repairOrder->commercial_pricing_profile) ? $repairOrder->commercial_pricing_profile : null,
        );
        $tax = TaxProfile::tryFromStored(
            is_array($repairOrder->tax_profile) ? $repairOrder->tax_profile : null,
        );

        // Terminal + missing profile: do not invent historical rules from live settings.
        $allowsLiveCommercialFallback = ! $terminal;

        if ($commercial === null && $allowsLiveCommercialFallback) {
            $commercial = CommercialPricingProfile::fromShopSettings($settings);
        }

        if ($shopFee === null && $allowsLiveCommercialFallback) {
            $shopFee = ShopFeeProfile::fromShopSettings($settings);
        }

        if ($tax === null && $allowsLiveCommercialFallback) {
            $tax = TaxProfile::fromShopSettings($settings);
        }

        return new self(
            repairOrder: $repairOrder,
            shopSettings: $settings,
            labor: $labor,
            shopFee: $shopFee,
            commercial: $commercial,
            tax: $tax,
            terminal: $terminal,
            allowsLiveCommercialFallback: $allowsLiveCommercialFallback,
        );
    }

    public function shopFeeProfile(): ShopFeeProfile
    {
        return $this->shopFee ?? ShopFeeProfile::fromShopSettings($this->shopSettings);
    }

    public function taxProfile(): ?TaxProfile
    {
        return $this->tax;
    }

    public function commercialProfile(): ?CommercialPricingProfile
    {
        return $this->commercial;
    }

    /**
     * True when this RO's commercial stamp carries frozen matrix definitions.
     * Unknown matrix keys must fail visibly — never silently use live ShopSettings.
     */
    public function hasFrozenPartsMatrices(): bool
    {
        return $this->commercial !== null && $this->commercial->partsMatrices !== [];
    }

    /**
     * @return array{type: string, percent: string}|null
     */
    public function standingDiscountPolicy(?string $customerType): ?array
    {
        if ($this->commercial === null) {
            return null;
        }

        return $this->commercial->standingDiscountPolicy($customerType);
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForCustomerType(?string $customerType): array
    {
        if ($this->commercial === null) {
            return ['enabled' => false, 'rate' => null];
        }

        return $this->commercial->shopFeePolicyForCustomerType($customerType);
    }

    public function documentPresentationFor(?string $customerType): CustomerDocumentPresentation
    {
        if ($this->commercial !== null) {
            return $this->commercial->documentPresentationFor($customerType);
        }

        if (! $this->allowsLiveCommercialFallback) {
            return new CustomerDocumentPresentation;
        }

        return $this->shopSettings->documentPresentationFor($customerType);
    }

    public function documentPresentationProfileFor(?string $customerType): CustomerPartPresentationProfile
    {
        $presentation = $this->documentPresentationFor($customerType);

        return match (true) {
            $presentation->showPartNumber && $presentation->showVendor => CustomerPartPresentationProfile::Warranty,
            $presentation->showPartNumber && ! $presentation->showVendor => CustomerPartPresentationProfile::RepairPal,
            default => CustomerPartPresentationProfile::Retail,
        };
    }

    /**
     * Resolve a parts matrix for this RO.
     *
     * When the RO carries its own commercial stamp, only stamped matrix definitions apply.
     * Explicit unknown keys return null (no silent default, no live shop fallback).
     * Schema-1 stamps without frozen tiers cannot reconstruct historical brackets; null.
     *
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>, is_active?: bool}|null
     */
    public function partsMatrixByKey(?string $key): ?array
    {
        $roHasCommercialStamp = is_array($this->repairOrder->commercial_pricing_profile)
            && $this->repairOrder->commercial_pricing_profile !== [];

        if ($roHasCommercialStamp) {
            if ($this->commercial === null) {
                return null;
            }

            // Explicit key: exact match only so post-open shop matrices cannot sneak in.
            if (filled($key)) {
                return $this->commercial->partsMatrixExact($key);
            }

            return $this->commercial->partsMatrixByKey(null);
        }

        if (! $this->allowsLiveCommercialFallback) {
            return null;
        }

        if ($this->commercial !== null) {
            if (filled($key)) {
                $exact = $this->commercial->partsMatrixExact($key);
                if ($exact !== null) {
                    return $exact;
                }
            } else {
                $fromSynthesized = $this->commercial->partsMatrixByKey(null);
                if ($fromSynthesized !== null) {
                    return $fromSynthesized;
                }
            }
        }

        return $this->shopSettings->partsMatrixByKey($key);
    }

    /**
     * @return array{
     *     repair_order_id: int|null,
     *     terminal: bool,
     *     allows_live_commercial_fallback: bool,
     *     has_frozen_parts_matrices: bool,
     *     labor_schema: int|null,
     *     shop_fee_schema: int|null,
     *     commercial_schema: int|null,
     *     commercial_version: int|null,
     *     tax_schema: int|null
     * }
     */
    public function provenance(): array
    {
        return [
            'repair_order_id' => $this->repairOrder->id,
            'terminal' => $this->terminal,
            'allows_live_commercial_fallback' => $this->allowsLiveCommercialFallback,
            'has_frozen_parts_matrices' => $this->hasFrozenPartsMatrices(),
            'labor_schema' => $this->labor?->schema,
            'shop_fee_schema' => $this->shopFee?->schema,
            'commercial_schema' => $this->commercial?->schema,
            'commercial_version' => $this->commercial?->version,
            'tax_schema' => $this->tax?->schema,
        ];
    }
}
